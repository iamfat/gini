<?php

class Q_Pseudo_Not implements Q_Pseudo {

	static $guid = 0;
	
	private $_query;

	function __construct($query) {
		$this->_query = $query;
	}

	function process($selector) {
		
		$query = $this->_query;
		if(!preg_match(Q_Query::PATTERN_NAME, $selector)){
			$selector = $query->name.$selector;
		}
		
		$not_query = new Q_Query($query->db);
		$not_query->parse_selector($selector);
		$not_query->makeSQL();
		if($not_query->name == $query->name){
			$db = $query->db;
			$query->where[] = $db->make_ident($query->table, 'id'). ' NOT IN (SELECT ' . $db->make_ident($not_query->table, 'id').' FROM '. $not_query->from_SQL. ') ';
		}
		
	}

}
