<?php

class Q_Pseudo_Alias implements Q_Pseudo {

	private $_query;

	function __construct($query) {
		$this->_query = $query;
	}


	function process($selector) {
		$this->_query->alias[$selector] = $query->table;	
	}
	
}
