<?php

namespace Gini;

class RPC
{
    private $_url;
    private $_path;
    private $_cookie;
    private $_uniqid = 1;

    public function __construct($url, $path=null, $cookie=null)
    {
        $this->_url = $url;
        $this->_path = $path;
        $this->_cookie = $cookie ?: IoC::construct('\Gini\RPC\Cookie');
    }

    public function __get($name)
    {
        return IoC::construct('\Gini\RPC', $this->_url, $this->_path ? $this->_path . '/' . $name : $name, $this->_cookie);
    }

    public function __call($method, $params)
    {
        if ($method === __FUNCTION__) return null;

        if ($this->_path) $method = $this->_path . '/' . $method;

        $id = base_convert($this->_uniqid ++, 10, 36);

        $raw_data = $this->post([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ]);

        $data = @json_decode($raw_data, true);
        if (!isset($data['result'])) {
            if (isset($data['error'])) {
                $message = sprintf('remote error: %s', $data['error']['message']);
                $code = $data['error']['code'];
                throw IoC::construct('\Gini\RPC\Exception', $message, $code);
            } elseif ($id != $data['id']) {
                $message = 'wrong response id!';
                throw IoC::construct('\Gini\RPC\Exception', $message);
            } elseif (is_null($data)) {
                $message = sprintf('unknown error with raw data: %s', $raw_data ?: '(null)');
                throw IoC::construct('\Gini\RPC\Exception', $message);
            }
        }

        return $data['result'];
    }

    public function post($post_data, $timeout = 5)
    {
        $cookie_file = $this->_cookie->file;

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_COOKIEJAR => $cookie_file,
            CURLOPT_COOKIEFILE => $cookie_file,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL => $this->_url,
            CURLOPT_AUTOREFERER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_FRESH_CONNECT => false,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?: 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
            CURLOPT_HTTPHEADER => array(
                'Content-Type' => 'application/json',
            ),
        ));

        $post_data = J($post_data);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        $data = curl_exec($ch);

        $errno = curl_errno($ch);
        if ($errno) {
            \Gini\Logger::of('core')->debug("RPC error: {error}", ['error'=>curl_error($ch)]);
            $data = null;
        }

        curl_close($ch);

        return $data;
    }

}
