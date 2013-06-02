<?php

class Macro {
	
	private $_content;

	function __construct($content) {
		$this->_content = $content;
	}

	function __toString() {
		return $this->_content;
	}

	static function compile($content) {
		return (string)(new Macro($content));
	}

}