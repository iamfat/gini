<?php

namespace Gini\CGI\Response;

class HTML implements \Gini\CGI\Response
{
    private $_content;
    private $_code;

    public function __construct($content, $code = 200)
    {
        $this->_content = $content;
        $this->_code = $code;
    }

    public function output($res = null)
    {
        if ($res) {
            $res->status($this->_code);
            $res->header('Content-Type: text/html; charset:utf-8');
            $res->end((string) $this->_content);
        } else {
            http_response_code($this->_code);
            header('Content-Type: text/html; charset:utf-8');
            file_put_contents('php://output', (string) $this->_content);
        }
    }

    public function content()
    {
        return $this->_content;
    }

    public function __toString()
    {
        return (string) $this->_content;
    }
}
