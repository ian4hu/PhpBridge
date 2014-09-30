<?php
error_reporting(E_ALL);
/**
 * PHP 代理
 * @author 胡继续
 *
 */
class PhpAgent
{

    protected $target = '';

    protected $prefix = '';

    public function __construct($target, $prefix = '')
    {
        $this->target = $target;
        $this->prefix = $prefix;
    }

    public function run()
    {
        $resp = $this->getResponse();
        foreach ($resp['headers'] as $header) {
            if (empty($header))
                continue;
            header($header);
        }
        echo $resp['body'];
    }

    protected function getTargetUrl()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $uri = substr($uri, strlen($this->prefix));
        return $this->target . $uri;
    }

    protected function getPost()
    {
        return file_get_contents('php://input');
    }

    protected function getHeaders()
    {
        $hks = [
            'HTTP_ACCEPT' => 'Accept',
            'HTTP_ACCEPT_CHARSET' => 'Accept-Charset',
            'HTTP_ACCEPT_ENCODING' => 'Accept-Encoding',
            'HTTP_ACCEPT_LANGUAGE' => 'Accept-Language',
            'HTTP_CONNECTION' => 'Connection',
            'HTTP_HOST' => 'Host',
            'HTTP_REFERER' => 'Referer',
            'HTTP_USER_AGENT' => 'User-Agent',
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_ENCODING' => 'Content-Encoding',
            'PHP_AUTH_DIGEST' => 'Authorization'
        ];
        $headers = [];
        foreach ($hks as $k => $v) {
            if (isset($_SERVER[$k])) {
                $headers[$v] = $_SERVER[$k];
            }
        }
        $ret = [];
        foreach ($headers as $k => $v) {
            $ret[] = $k . ': ' . $v;
        }
        return $ret;
    }

    protected function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    protected function isGet()
    {
        return $_SERVER['REQUEST_METHOD'] == 'GET';
    }

    protected function getOptions()
    {
        $options = [
            CURLOPT_DNS_USE_GLOBAL_CACHE => true,
            CURLOPT_AUTOREFERER => false,
            CURLOPT_HTTPHEADER => $this->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_URL => $this->getTargetUrl(),
            CURLOPT_DNS_CACHE_TIMEOUT => 1800,
            CURLOPT_CONNECTTIMEOUT => 60,
            CURLOPT_TIMEOUT => 60
        ];
        if ($this->isPost()) {
            $options[CURLOPT_POSTFIELDS] = $this->getPost();
            $options[CURLOPT_POST] = true;
            if (isset($_FILES) && ! empty($_FILES)) {
                $options[CURLOPT_UPLOAD] = true;
                $options[CURLOPT_POSTFIELDS] = [];
                foreach ($_POST as $k => $v) {
                    $options[CURLOPT_POSTFIELDS][$k] = $v;
                }
                foreach ($_FILES as $k => $v) {
                    if (! is_array($v['error'])) {
                        $options[CURLOPT_POSTFIELDS][$k] = '@' . $v['tmp_name'];
                        break;
                    }
                    // multi files
                    $options[CURLOPT_POSTFIELDS][$k] = [];
                    foreach ($v['error'] as $i => $e) {
                        $options[CURLOPT_POSTFIELDS][$k][] = '@' . $v['tmp_name'][$i];
                    }
                }
            }
        }
        return $options;
    }

    protected function getResponse()
    {
        $response = [
            'headers' => [],
            'body' => ''
        ];
        $curl = curl_init();
        curl_setopt_array($curl, $this->getOptions());
        $html = curl_exec($curl);
        if ($html) {
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $header = substr($html, 0, $header_size);
            $headers = explode("\r\n", $header);
            $response['headers'] = $headers;
            $html = substr($html, $header_size);
            $response['body'] = $html;
        }
        curl_close($curl);
        return $response;
    }
}

$agent = new PhpAgent('http://thinkgeek.vicp.net:48080/canvas/', '');
$agent->run();
echo 'fetched by ',$_SERVER['HOST'];
