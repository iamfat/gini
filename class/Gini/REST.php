<?php

namespace Gini;

/*
$rest = new \Gini\REST('http://localhost:3000/rest');
$response = $rest->get('hello/article/1');
$response = $rest->json()->post('hello/article', ['author'=>'libai']);
$response = $rest->form()->post('hello/article', ['author'=>'libai']);
*/

class REST
{
    private $_url;
    public $timeout = 5;

    private static $supportedMethods = ['get', 'post', 'delete', 'put', 'patch'];

    public function __construct($url)
    {
        $this->_url = rtrim($url, '/');
        $this->_http = IoC::construct('\Gini\HTTP');
        $this->json();
    }

    public function __call($method, $params)
    {
        if ($method === __FUNCTION__) {
            return;
        }

        if (in_array($method, self::$supportedMethods)) {
            list($path, $query, $timeout) = $params;
            $query or $query = [];
            $timeout or $timeout = $this->timeout;

            $url = $this->_url . '/' . ltrim($path, '/');
            $response = $this->_http->request($method, $url, $query, $this->timeout);
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

    public function json()
    {
        $this->_http->header('Content-Type', 'application/json');
        return $this;
    }

    public function form()
    {
        $this->_http->header('Content-Type', 'application/x-www-form-urlencoded');
        return $this;
    }
}
