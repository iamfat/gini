<?php

namespace Model;

class RPC_Exception extends Exception {}

register_shutdown_function('RPC::remove_cookie_file');

class RPC {

	private $_url;
	private $_path;

	public $sess_id;

	static function cookie_file($id=0) {
		static $files;
		if ($files[$id] === NULL) {
			$files[$id] = $file = tempnam(sys_get_temp_dir(), 'rpc.cookie.');
		}
		return $files[$id];
	}

	static function remove_cookie_file() {
		@unlink(self::cookie_file());
	}
	
	function __construct($url, $path=NULL) {
		$this->_url = $url;
		$this->_path = $path;
	}

	function __get($name) {
		return new RPC($this->_url, $this->_path ? $this->_path . '/' . $name : $name);
	}

	function __call($method, $params) {
		if ($method === __FUNCTION__) return NULL;

		if ($this->_path) $method = $this->_path . '/' . $method;

		$id = uniqid();

		$raw_data = $this->post(array(
			'jsonrpc' => '2.0',
			'method' => $method,
			'params' => $params,
			'id' => $id,
		));

		$data = @json_decode($raw_data, TRUE);
		if (!isset($data['result'])) {
			if (isset($data['error'])) {
				$message = sprintf('remote error: %s', $data['error']['message']);
				$code = $data['error']['code'];
				throw new RPC_Exception($message, $code);
			}
			elseif ($id != $data['id']) {
				$message = 'wrong response id!';
				throw new RPC_Exception($message);
			}
			elseif (is_null($data)) {
				$message = sprintf('unknown error with raw data: %s', $raw_data ?: '(NULL)');
				throw new RPC_Exception($message);
			}
		}

		return $data['result'];
	}

	function post($post_data, $timeout = 5) {

		$cookie_file = self::cookie_file($this->sess_id);

		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_COOKIEJAR => $cookie_file,
			CURLOPT_COOKIEFILE => $cookie_file,
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_URL => $this->_url,
			CURLOPT_AUTOREFERER => FALSE,
			CURLOPT_FOLLOWLOCATION => FALSE,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_RETURNTRANSFER => TRUE,
			// CURLOPT_FRESH_CONNECT => FALSE,
			CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?: 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
			CURLOPT_HTTPHEADER => array(
				'Content-Type' => 'application/json',
			),
		));

		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, @json_encode($post_data));

		$data = curl_exec($ch);
		
		$errno = curl_errno($ch);
		if ($errno) {
			$err = curl_error($ch);
			error_log("CURL ERROR: $err");
			$data = NULL;
		}

		curl_close($ch);
		return $data;
	}

}




