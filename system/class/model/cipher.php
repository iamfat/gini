<?php

namespace Model;

final class Cipher {

	static function encrypt($text, $salt, $base64=FALSE, $mode = 'blowfish') {
		if(!$salt) return $text;
		$code = @openssl_encrypt($text, $mode, $salt, !$base64);
		return $code;
	}

	static function decrypt($code, $salt, $base64=FALSE, $mode = 'blowfish') {
		if(!$salt) return $code;
		$text = @openssl_decrypt($code, $mode, $salt, !$base64);
		return $text;
	}
	
}
