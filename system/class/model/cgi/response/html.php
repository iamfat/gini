<?php

namespace Model\CGI\Response {

	class HTML {

		private $_data;

		function __construct($view) {
			$this->_data = (string) $view;
		}

		function output() {
			header('Content-Type: text/html');
			file_put_contents('php://output', $this->_data);
		}

	}

}