<?php

class Q_Pseudo_Union implements Q_Pseudo {

	private $_query;

	function __construct($query) {
		$this->_query = $query;
	}

	function process($selector) {
		$query = $this->_query;
		if(!preg_match(Q_Query::PATTERN_NAME, $selector)){
			$selector = $query->name.$selector;
		}

		$union_query = new Q_Query($query->db);
		$union_query->parse_selector($selector);
		$union_query->makeSQL();
		if($union_query->name == $query->name){
			$db = $query->db;
			$query->union[] = 'SELECT '. $db->quote_ident($union_query->table) .'.id FROM ' . $union_query->from_SQL;
		}
	}

}
