<?php

namespace Gini;

/*
$rest = new \Gini\REST('http://localhost:3000/rest');
$response = $rest->get('hello/article/1');
$response = $rest->post('hello/article', ['author'=>'libai', 'title'=>'jiangjinjiu', 'body'=>'balabala']);
*/

class REST
{
    private $_url;
    public $timeout = 5;

    private static $supportedMethods = ['get', 'post', 'delete', 'put', 'options'];

    public function __construct($url, $cookie = null, $headers = [])
    {
        $this->_url = rtrim($url, '/');
        $this->_cookie = $cookie ?: IoC::construct('\Gini\HTTP\Cookie');
        $this->_headers = (array) $headers;
        $this->_http = IoC::construct('\Gini\HTTP');
    }

    public function __call($method, $params)
    {
        if ($method === __FUNCTION__) {
            return;
        }

        if ($this->_path) {
            $method = $this->_path.'/'.$method;
        }

        if (in_array($method, self::$supportedMethods)) {
            list($path, $query, $timeout) = $params;
            $query or $query = [];
            $timeout or $timeout = $this->timeout;

            $url = $this->_url . '/' . ltrim($path, '/');
            $response = $this->_http->$method($url, $query, $this->timeout);
            $status = $response->status();

            $raw_data = (string) $response;
            \Gini\Logger::of('rest')->debug('REST <= {data}', ['data' => $raw_data]);

            $data = @json_decode($raw_data, true);
            if (intval($status->code / 100) !== 2) {
                throw  IoC::construct('\Gini\REST\Exception', $status->text, $status->code, $data);
            }
    
            return $data;
        }
    }

    public function header($name, $value)
    {
        $this->_http->header($name, $value);
        return $this;
    }
}
