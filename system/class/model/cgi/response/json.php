<?php

namespace Model\CGI\Response {

    class JSON implements \Model\CGI\Response {

        private $_content;

        function __construct($content) {
            $this->_content = $content;
        }

        function output() {
            header('Content-Type: application/json');
            if ($this->_content !== null) {
                file_put_contents('php://output', json_encode($this->_content)."\n");
            }
        }
        
        function content() {
            return $this->_content;
        }

    }

}