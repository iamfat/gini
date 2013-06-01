<?php

namespace Model {

	class RSA {

		private $_public_key;
		private $_private_key;

		function __construct($key) {
			list($proto, $name) = explode('://', $key, 2);
			if ($name && $proto == 'data') {
				// is a key file 
				$file = \Gini\Core::phar_file_exists(DATA_DIR, $name);
				if ($file) {
					$content = file_get_contents($file);
					if (FALSE === strpos($content, 'PRIVATE KEY')) {
						$this->_public_key = openssl_pkey_get_public($content);
					}
					else {
						$this->_private_key = openssl_pkey_get_private($content);
					}
				}
			}
		}

		function encrypt($source, $base64=TRUE) {

			if ($this->_private_key) {
				openssl_private_encrypt($source, $code, $this->_private_key);
			}
			elseif ($this->_public_key) {
				openssl_public_encrypt($source, $code, $this->_public_key);
			}

			if ($base64) {
				$code = base64_encode($code);
			}

			return $code;
		}

		function decrypt($code, $base64=TRUE) {

			if ($base64) {
				$code = base64_decode($code);
			}

			if ($this->_private_key) {
				openssl_private_decrypt($code, $source, $this->_private_key);
			}
			elseif ($this->_public_key) {
				openssl_public_decrypt($code, $source, $this->_public_key);
			}

			return $source;
		}

		function sign($source, $base64=TRUE) {
			
			if ($this->_private_key) {
				openssl_sign($source, $signature, $this->_private_key);
			}

			if ($base64) {
				$signature = base64_encode($signature);
			}

			return $signature;
		}

		function verify($source, $signature, $base64=TRUE) {

			if ($base64) {
				$signature = base64_decode($signature);
			}

			if ($this->_public_key) {
				$verified = openssl_verify($source, $signature, $this->_public_key);
			}

			return !!$verified;
		}

		function public_key() {
			if ($this->_private_key) {
				return openssl_pkey_get_details($this->_private_key)['key'];
			}
		}

	}


}