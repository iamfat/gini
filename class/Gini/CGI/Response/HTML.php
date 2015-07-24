<?php

namespace Gini\CGI\Response;

class HTML implements \Gini\CGI\Response
{
    private $_content;
    private $_charset;

    public function __construct($content, $charset='utf-8')
    {
        $this->_content = $content;
        $this->_charset = $charset;
    }

    public function output()
    {
        header('Content-Type: text/html; charset:' . $this->_charset);
        file_put_contents('php://output', (string) $this->_content);
    }

    public function content()
    {
        return $this->_content;
    }
}
