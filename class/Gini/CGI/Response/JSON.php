<?php

namespace Gini\CGI\Response;

class JSON implements \Gini\CGI\Response
{
    private $_content;

    public function __construct($content)
    {
        $this->_content = $content;
    }

    public function output()
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($this->_content !== null) {
            file_put_contents('php://output',
                    J($this->_content).PHP_EOL);
        }
    }

    public function content()
    {
        return $this->_content;
    }
}
