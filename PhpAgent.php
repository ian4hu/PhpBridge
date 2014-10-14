<?php

abstract class PhpBridgeInterface
{

    public function __construct()
    {}

    /**
     * 获取需要转发的情求头
     *
     * @return array
     */
    public static function getRequestHeaders()
    {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name = join('-', array_map('ucfirst', explode('_', strtolower(substr($name, 5)))));
                $headers[$name] = $value;
            } elseif ($name == "CONTENT_TYPE") {
                $headers["Content-Type"] = $value;
            } elseif ($name == "CONTENT_LENGTH") {
                $headers["Content-Length"] = $value;
            }
        }
        return $headers;
    }

    /**
     * 进行转发
     *
     * @param string $target
     */
    public static function bridgeTo($target, $timeout = 60);

    public static function onFailed($target, $message)
    {
        if (! headers_sent()) {
            header('HTTP/1.1 502 Gateway Error');
            header('Content-Type: text/plain');
        }
        echo "502 Urlfetch Error\r\nPHP Urlfetch Error: $message";
    }
}

class CurlPhpBridge extends PhpBridgeInterface
{

    public static function bridgeTo($target, $timeout = 60)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $headers = static::getRequestHeaders();
        $body = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        if ($body && ! isset($headers['Content-Length'])) {
            $headers['Content-Length'] = strval(strlen($body));
        }
        if (isset($headers['Connection'])) {
            $headers['Connection'] = 'close';
        }
        // $headers['Host'] = $urlparts['host'];
        
        $header_array = array();
        foreach ($headers as $key => $value) {
            $header_array[] = "$key: $value";
        }
        
        $curl_opt = array();
        
        switch (strtoupper($method)) {
            case 'HEAD':
                $curl_opt[CURLOPT_NOBODY] = true;
                break;
            case 'GET':
                break;
            case 'POST':
                $curl_opt[CURLOPT_POST] = true;
                $curl_opt[CURLOPT_POSTFIELDS] = $body;
                break;
            default:
                $curl_opt[CURLOPT_CUSTOMREQUEST] = $method;
                $curl_opt[CURLOPT_POSTFIELDS] = $body;
                break;
        }
        
        $curl_opt[CURLOPT_HTTPHEADER] = $header_array; // 请求头
        $curl_opt[CURLOPT_RETURNTRANSFER] = true; //
        $curl_opt[CURLOPT_BINARYTRANSFER] = true;
        
        $curl_opt[CURLOPT_HEADER] = false; // 不返回响应头
        $curl_opt[CURLOPT_HEADERFUNCTION] = function ($ch, $header)
        {
            if (stripos($header, 'Transfer-Encoding:') === false) {
                header($header, false);
            }
            return strlen($header);
        };
        $curl_opt[CURLOPT_WRITEFUNCTION] = function ($ch, $content)
        {
            echo $content;
            return strlen($content);
        };
        
        $curl_opt[CURLOPT_FAILONERROR] = false;
        $curl_opt[CURLOPT_FOLLOWLOCATION] = false;
        
        $curl_opt[CURLOPT_CONNECTTIMEOUT] = $timeout;
        $curl_opt[CURLOPT_TIMEOUT] = $timeout;
        $curl_opt[CURLOPT_DNS_USE_GLOBAL_CACHE] = true;
        
        $curl_opt[CURLOPT_SSL_VERIFYPEER] = false;
        $curl_opt[CURLOPT_SSL_VERIFYHOST] = false;
        
        $ch = curl_init($target);
        curl_setopt_array($ch, $curl_opt);
        $ret = curl_exec($ch);
        $errno = curl_errno($ch);
        
        if ($errno) {
            static::onFailed($target, "curl($errno)\r\n");
            // echo "502 Urlfetch Error\r\nPHP Urlfetch Error: . curl_error($ch);
        }
        curl_close($ch);
    }
}

class StreamPhpBridge extends PhpBridgeInterface
{

    public static function bridgeTo($target, $timeout = 60)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $headers = static::getRequestHeaders();
        $body = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        if ($body && ! isset($headers['Content-Length'])) {
            $headers['Content-Length'] = strval(strlen($body));
        }
        if (isset($headers['Connection'])) {
            $headers['Connection'] = 'close';
        }
        // $headers['Host'] = $urlparts['host'];
        
        $header_array = array();
        foreach ($headers as $key => $value) {
            $header_array[] = "$key: $value";
        }
        
        $context_opts = array(
            'http' => array(
                'method' => $method,
                'header' => implode('\r\n', $header_array),
                'content' => $body,
                'timeout' => $timeout
            )
        );
        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                break;
            case 'HEAD':
                break;
            default:
                break;
        }
        
        $context = stream_context_create($context_opts);
        global $php_errormsg;
        $ret = file_get_contents($target, false, $context);
        $message = $php_errormsg;
        if ($ret === false) {
            static::onFailed($target, $message);
            return;
        }
        $ret_headers = $http_response_header;
        foreach ($ret_headers as $header) {
            if (stripos($header, 'Transfer-Encoding:') === false) {
                header($header, false);
            }
        }
        echo $ret;
    }
}
$prefix = '/canvas';
$uri = str_replace($prefix, "", $_SERVER['REQUEST_URI']);
StreamPhpBridge::bridgeTo('http://prolove.duapp.com/canvas' . $uri);
