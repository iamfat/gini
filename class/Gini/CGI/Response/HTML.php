<?php

namespace Gini\CGI\Response;

    class HTML
    {
        private $_content;

        function __construct($content)
        {
            $this->_content = $content;
        }

        function output()
        {
            header('Content-Type: text/html');
            file_put_contents('php://output', (string) $this->_content);
        }

        function content()
        {
            return $this->_content;
        }

    }
