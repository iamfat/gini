<?php

namespace Model\Database;

use \Model\Config;

final class MySQL implements \Model\Database\Handler {

	private $_table_status = NULL;
	private $_table_schema = NULL;

	private $_info;

	private $_h;

	function __construct($info){
		$this->_info = $info;
		$this->connect();
	}
	
	function connect() {
		$this->_h = new \mysqli(
			$this->_info['host'], 
			$this->_info['user'], $this->_info['password'],
			$this->_info['db'],
			$this->_info['port']
		);

		if ($this->_h->connect_errno) {
			throw new \ErrorException('database connect error');
		} 
		else {
			$this->_h->set_charset('utf8');
		}

	}
	
	function is_connected() {
		return $this->_h ? $this->_h->connect_errno == 0 : FALSE;
	}

	function escape($s) {
		return $this->_h ? $this->_h->escape_string($s) : addslashes($s);
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

	function rewrite(){
		$args = func_get_args();	
		$SQL = array_shift($args);
		foreach($args as $k=>&$v){
			if (is_array($v)) {
				$v=$this->quote($v);
			}
			elseif (is_bool($v) || is_int($v) || is_float($v)){
			} 
			else {
				$v=$this->escape($v);
			} 
		}
		return vsprintf($SQL, $args);	
	}
	
	function query($SQL) {
		$retried = 0;

		while (1) {
			$result = @$this->_h->query($SQL);
			if (is_object($result)) return new DBResult($result);

			if ($this->_h->errno != 2006) break;
			if ($retried > 0) {
				trigger_error('database gone away!');
			}

			$this->connect();
			$retried ++;
		}

		return $result;
	}

	function insert_id() {
		return @$this->_h->insert_id;
	}

	function affected_rows() {
		return @$this->_h->affected_rows;
	}

	private function _update_table_status($table=NULL) {
		if ($table || !$this->_table_status) {
			
			if ($table && $table != '*') {
				unset($this->_table_status[$table]);
				$SQL = $this->rewrite('SHOW TABLE STATUS FROM `%s` WHERE `Name`="%s"', $this->_info['db'], $table);
			}
			else {
				$this->_table_status = NULL;
				$SQL = $this->rewrite('SHOW TABLE STATUS FROM `%s`', $this->_info['db']);
			}

			$rs = $this->query($SQL);
			while ($r = $rs->row()) {
				$this->_table_status[$r->Name] = (object) array(
					'engine' => strtolower($r->Engine),
					'collation' => $r->Collation,
				);
			}
		}
	}

	function table_exists($table){
		return isset($this->_table_status[$table]);
	}

	function table_status($table) {
		$this->_update_table_status();
		return $this->_table_status[$table];
	}

	private static function _normalize_type($type) {
		// 确保小写
		$type = strtolower($type);
		// 移除多余空格
		$type = preg_replace('/\s+/', ' ', $type); 
		// 去除多级整数的长度说明
		$type = preg_replace('/\b(tinyint|smallint|mediumint|bigint|int)\s*\(\s*\d+\s*\)/', '$1', $type);
		
		return $type;
	}

	function adjust_table($table, $schema) {
		
		$remove_nonexistent = _CONF('database.remove_nonexistent') ?: FALSE;
		
		if (!$this->table_exists($table)){
			$this->create_table($table);
		}

		$field_sql = array();

		$fields = $schema['fields'];
		$indexes = $schema['indexes'];

		$curr_schema = $this->table_schema($table);
		//检查所有Fields
		$curr_fields = $curr_schema['fields'];
		$missing_fields = array_diff_key($fields, $curr_fields);
		foreach ($missing_fields as $key=>$field) {
			$field_sql[]='ADD '.$this->field_sql($key, $field);
		}
		
		foreach ($curr_fields as $key=>$curr_field) {
			$field = $fields[$key];
			if ($field) {
				$curr_type = $this->_normalize_type($curr_field['type']);
				$type = $this->_normalize_type($field['type']);
				if ( $type !== $curr_type
					|| $field['null'] != $curr_field['null']
					|| $field['default'] != $curr_field['default']
					|| $field['auto_increment'] != $curr_field['auto_increment']) {
					$field_sql[] = sprintf('CHANGE %s %s'
						, $this->quote_ident($key)
						, $this->field_sql($key, $field));
				}
			}
			elseif ($remove_nonexistent) {
				$field_sql[] = sprintf('DROP %s', $this->quote_ident($key) );
			}
			/*
			elseif ($key[0] != '@') {
				$nkey = '@'.$key;
				while (isset($curr_fields[$nkey])) {
					$nkey .= '_';
				}

				$field_sql[] = sprintf('CHANGE %s %s'
					, $this->quote_ident($key)
					, $this->field_sql($nkey, $curr_field));
			}
			*/
		}

		$curr_indexes = $curr_schema['indexes'];
		$missing_indexes = array_diff_key($indexes, $curr_indexes);
		foreach($missing_indexes as $key=>$val) {
			$field_sql[] = sprintf('ADD %s'
				, $this->alter_index_sql($key, $val));
		}
		
		foreach($curr_indexes as $key=>$curr_val) {
			$val = & $indexes[$key];
			if ($val) {
				if ( $val['type'] != $curr_val['type']
					|| array_diff($val, $curr_val)) {

					$field_sql[]=sprintf('DROP %s, ADD %s'
						, $this->alter_index_sql($key, $curr_val, TRUE)
						, $this->alter_index_sql($key, $val));
				}
			}
			else/*if ($remove_nonexistent)*/ {
				$field_sql[]=sprintf('DROP INDEX %s', $this->quote_ident($key) );
			}
		}

		if (count($field_sql)>0) {
			$this->query('ALTER TABLE '.$this->quote_ident($table).' '.implode(', ', $field_sql));
			$this->table_schema($table, TRUE);
		}

	}

	function table_schema($name, $refresh = FALSE) {
		
		if($refresh || !isset($this->_table_schema[$name]['fields'])){
			$ds = $this->query($this->rewrite('SHOW FIELDS FROM `%s`', $name));
			$fields=array();
			if ($ds) while($dr = $ds->row('object')) {

				$field = array('type' => $this->_normalize_type($dr->Type));

				if ($dr->Default !== NULL) {
					$field['default'] = $dr->Default;
				}

				if ($dr->Null != 'NO') {
					$field['null'] = TRUE;
				}				

				if (FALSE !== strpos($dr->Extra, 'auto_increment')) {
					$field['auto_increment'] = TRUE;
				}

				$fields[$dr->Field] = $field;
			}

			$this->_table_schema[$name]['fields'] = $fields;
		}
		
		if ($refresh || !isset($this->_table_schema[$name]['indexes'])) {
			$ds=$this->query($this->rewrite('SHOW INDEX FROM `%s`', $name));
			$indexes=array();
			if ($ds) while($row = $ds->row('object')) {
				$indexes[$row->Key_name]['fields'][] = $row->Column_name;
				if (!$row->Non_unique) {
					$indexes[$row->Key_name]['type'] = $row->Key_name == 'PRIMARY' ? 'primary' : 'unique';
				}
			}
			
			$this->_table_schema[$name]['indexes'] = $indexes;
		}

		return $this->_table_schema[$name];
	}

	private function field_sql($key, &$field) {
		return sprintf('%s %s%s%s%s'
				, $this->quote_ident($key)
				, $field['type']
				, $field['null']? '': ' NOT NULL'
				, isset($field['default']) ? ' DEFAULT '.$this->quote($field['default']):''
				, $field['auto_increment'] ? ' AUTO_INCREMENT':''
				);
	}
	
	private function index_sql($key, &$val, $no_fields = FALSE) {
		switch($val['type']){
		case 'primary':
			$type='PRIMARY KEY';
			break;
		case 'unique':
			$type='UNIQUE KEY '. $this->quote_ident($key);
			break;
		default:
			$type='KEY '. $this->quote_ident($key);
		}
		
		if ($no_fields) {
			return $type;
		}
		else {
			return sprintf('%s (%s)', $type, $this->quote_ident($val['fields']));
		}
	}
	
	private function alter_index_sql($key, &$val, $no_fields = FALSE) {
		switch($val['type']){
		case 'primary':
			$type='PRIMARY KEY';
			break;
		case 'unique':
			$type='UNIQUE '. $this->quote_ident($key);
			break;
		default:
			$type='INDEX '. $this->quote_ident($key);
		}
		
		if ($no_fields) {
			return $type;
		}
		else {
			return sprintf('%s (%s)', $type, $this->quote_ident($val['fields']));
		}
	}
	
	function create_table($table, $engine=NULL) {
		 
		$engine = $engine ?: 'innodb';	//innodb as default db
		
		$SQL = $this->rewrite('CREATE TABLE `%s` (`%s` int NOT NULL) ENGINE = %s DEFAULT CHARSET = utf8', $table, '_FOO', $engine);
		$rs = $this->query($SQL);
		$this->_update_table_status($table);
		
		return $rs !== NULL;
	
	}

	function begin_transaction() {
		@$this->_h->autocommit(FALSE);
	}
	
	function commit() {
		@$this->_h->commit();
		@$this->_h->autocommit(TRUE);
	}
	
	function rollback() {
		@$this->_h->rollback();
		@$this->_h->autocommit(TRUE);
	}
	
	function drop_table($table) {
		$this->query('DROP TABLE '.$this->quote_ident($table));
		$this->_update_table_status($table);
		unset($this->_prepared_tables[$table]);
		unset($this->_table_fields[$table]);
		unset($this->_table_indexes[$table]);		
	}
	
	function snapshot($filename, $tables) {
		
		$tables = (array)$tables;
		foreach ($tables as &$table) {
			$table = escapeshellarg($table);
		}
	
		$table_str = implode(' ', $tables);
	
		$dump_command = sprintf('/usr/bin/mysqldump -h %s -u %s %s %s %s > %s', 
				escapeshellarg($this->_info['host']),
				escapeshellarg($this->_info['user']),
				$this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
				escapeshellarg($this->_info['db']),
				$table_str,
				escapeshellarg($filename)
				);	
		exec($dump_command, $output=NULL, $ret);
		return $ret == 0;
	}
	
	function empty_database() {
		$rs = $this->query('SHOW TABLES');
		while ($r = $rs->row('num')) {
			$tables[] = $r[0];
		}
		$this->query('DROP TABLE '.$this->quote_ident($tables));
	}
	
	function restore($filename, &$retore_filename, $tables) {
		
		$import_command = sprintf('/usr/bin/mysql -h %s -u %s %s %s < %s', 
				escapeshellarg($this->_info['host']),
				escapeshellarg($this->_info['user']),
				$this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
				escapeshellarg($this->_info['db']),
				escapeshellarg($filename)
				);	
		exec($import_command, $output=NULL, $ret);
		
		return $ret == 0;
	}
	
}

class DBResult implements \Model\Database\Result {
	private $_result;
	
	function __construct($result){
		$this->_result=$result;
	}
	
	function rows($mode='object') {
		$rows = array();
		while ($row = $this->row($mode)) {
			$rows[] = $row;
		}
		return $rows;
	}
	
	function row($mode='object'){
		if ($mode == 'assoc') {
			return $this->_result->fetch_assoc();
		}elseif ($mode == 'num') {
			return $this->_result->fetch_row();
		}elseif ($mode == 'object') {
			return $this->_result->fetch_object();
		}		
		return $this->_result->fetch_array(MYSQL_BOTH);
	}
	
	function count(){
		return is_object($this->_result) ? $this->_result->num_rows : 0;
	}

	function value(){
		$r = $this->row('num');
		if(!$r)return NULL;
		return $r[0];
	}
	
	function object(){
		$r = $this->row('object');
		if(!$r)return NULL;
		return $r;
	}
	
	function __destruct(){
		if (is_object($this->_result)) $this->_result->free();
	}

}
