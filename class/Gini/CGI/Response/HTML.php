<?php

namespace Gini\CGI\Response;

class HTML
{
    private $_content;

    public function __construct($content)
    {
        $this->_content = $content;
    }

    public function output()
    {
        header('Content-Type: text/html');
        file_put_contents('php://output', (string) $this->_content);
    }

    public function content()
    {
        return $this->_content;
    }
}
