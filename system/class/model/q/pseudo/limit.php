<?php

class Q_Pseudo_Limit implements Q_Pseudo {

	static $guid = 0;

	private $_query;

	function __construct($query) {
		$this->_query = $query;
	}

	//:limit(0,5)
	function process($selector) {
		$this->_query->limit = preg_replace('/[^0-9-,\s]/', '', $selector);
	}

}

