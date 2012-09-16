<?php

//TODO: PosgreSQL目前还不支持呢...

namespace Model\Database;

use \Model\Config;

final class pgSQL implements \Model\Database\Driver {

	private $_info;
	private $_h;

	function __construct($info){
		$this->_info = $info;
		$this->connect();
	}
	
	function connect() {

	}
	
	function is_connected() {

	}

	function escape($s) {
		// return $this->_h ? $this->_h->escape_string($s) : addslashes($s);
	}

	function quote_ident($s){
		if (is_array($s)) {
			foreach($s as &$i){
				$i = $this->quote_ident($i);
			}
			return implode(',', $s);
		}		
		return '`'.$this->escape($s).'`';
	}
	
	function quote($s) {
		if(is_array($s)){
			foreach($s as &$i){
				$i=$this->quote($i);
			}			
			return implode(',', $s);
		}
		elseif (is_bool($s) || is_int($s) || is_float($s)) {
			return $s;
		}
		return '\''.$this->escape($s).'\'';
	}

	function query($SQL) {
		return new \Model\Database\Result($this, NULL);
	}

	function insert_id() {

	}

	function affected_rows() {

	}

	function table_exists($table){
		return FALSE;
	}

	function table_status($table) {
		return array('engine'=>'unknown', 'collation'=>'utf8');
	}

	function adjust_table($table, $schema) {
		
	}

	function table_schema($name, $refresh = FALSE) {
		return array();
	}

	function create_table($table, $engine=NULL) {
		return FALSE;
	}

	function begin_transaction() {
	}
	
	function commit() {
	}
	
	function rollback() {
	}
	
	function drop_table($table) {
	}
	
	function snapshot($filename, $tables) {
		return FALSE;		
	}
	
	function empty_database() {

	}
	
	function restore($filename, &$retore_filename, $tables) {
		
		return FALSE;
	}

	function fetch_row($result, $mode='object') {
		return array();		
	}

	function num_rows($result) {
		return 0;
	}
	
}
