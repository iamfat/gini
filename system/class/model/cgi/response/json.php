<?php

namespace Model\CGI\Response {

	class JSON implements \Model\CGI\Response {

		private $_data;

		function __construct($data) {
			$this->_data = $data;
		}

		function output() {
			header('Content-Type: application/json');
			file_put_contents('php://output', json_encode($this->_data)."\n");
		}

	}

}