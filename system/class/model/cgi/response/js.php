<?php

namespace Model\CGI\Response {

	class JS implements \Model\CGI\Response {

		private $_data;

		function __construct($data) {
			$this->_data = $data;
		}

		function output() {
			header('Content-Type: text/javascript');
			file_put_contents('php://output', $this->_data);
		}

	}

}