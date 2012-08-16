<?php

class Q_Pseudo_Sort implements Q_Pseudo {

	static $guid = 0;
	
	const SORT_PATTERN = '/([\w\pL_-]+)\s*(DESC|ASC|↓|D|A|↑)?(\s*,\s*)?/';

	private $_query;

	function __construct($query) {
		$this->_query = $query;
	}

	function process($selector) {
		$query = $this->_query;
		if (preg_match_all(self::SORT_PATTERN, $selector, $parts, PREG_SET_ORDER)) {
			$db = $query->db;
			foreach($parts as $part){
				$field = $part[1];
				$order = $part[2];
				$order=preg_match('/^↓|D|DESC$/', $order) ? 'DESC':'ASC';
				$query->order_by[] = $db->make_ident($query->table, $field).' '.$order;
			}
		}
	}
	
}
