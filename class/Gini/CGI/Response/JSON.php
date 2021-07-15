<?php

namespace Gini\CGI\Response;

class JSON implements \Gini\CGI\Response
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
            $res->header('Content-Type: application/json; charset=utf-8');
            $res->end(J($this->_content));
        } else {
            http_response_code($this->_code);
            header('Content-Type: application/json; charset=utf-8');
            if ($this->_content !== null) {
                file_put_contents(
                    'php://output',
                    J($this->_content) . PHP_EOL
                );
            }
        }
    }

    public function content()
    {
        return $this->_content;
    }

    public function __toString()
    {
        return J($this->_content);
    }
}
