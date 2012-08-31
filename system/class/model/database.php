<?php

namespace Model\Database {

	interface Handler {

		function __construct($info);

		function escape($s);
		function quote($s);
		function quote_ident($s);

		function query($SQL);

		function insert_id();
		function affected_rows();

		function table_exists($table);
		function table_status($table);
		function table_schema($name, $refresh);

		function adjust_table($table, $schema);

		function begin_transaction();
		function commit();
		function rollback();
		function snapshot($filename, $tbls);
		function empty_database();

		function create_table($table);
		function drop_table($table);

		function restore($filename, &$restore_filename, $tables);
	}

	interface Result {
		function rows($mode/*='object'*/);
		function row($mode/*='object'*/);
		function count();
		function value();
	}

}

namespace Model {
	
	use \Model\Config;
	use \Model\Log;
	
	final class Database {
	
		static $DB = array();
		static $query_count = 0;
		static $cache_hits = 0;
	
		private $_handle;
		private $_url;
		private $_info;
		
		private $_name;
	
		static function & db($name=NULL) {
		
			$name = $name ?:_CONF('database.default');
		
			if(!isset(self::$DB[$name])){
				$url = _CONF('database.'.$name.'.url');
				if (!$url) {
					$dbname = _CONF('database.'.$name.'.db');
					if (!$dbname) $dbname = _CONF('database.prefix') . $name;
					$url = strtr(_CONF('database.root'), array('%database' => $dbname));
				}
				self::$DB[$name] = new Database($url);
				self::$DB[$name]->name($name);
			}
		
			return self::$DB[$name];
		}	
		
		static function shutdown($name=NULL) {
			if(!$name) $name = _CONF('database.default');
		
			if(!isset(self::$DB[$name])){
				unset(self::$DB[$name]);
			}
		}
	
		static function reset() {
			self::$DB = array();
		}
		
		function __construct($url=NULL){
			$this->_url = $url;
			$url = parse_url($url);
	
			$this->_info['handler'] = $url['scheme'];	
			$this->_info['host']= urldecode($url['host']);
			$this->_info['port'] = (int)$url['port'];
			$this->_info['db'] = substr(urldecode($url['path']), 1);
			$this->_info['user'] = urldecode($url['user']);
			$this->_info['password']  = isset($url['pass']) ? urldecode($url['pass']) : NULL;
			
			$this->connect();
		}
	
		function info() {
			return $this->_info;
		}
		
		function connect() {
			$handler = '\Model\Database\\'.$this->_info['handler'];
			$this->_handle = new $handler($this->_info);
		}
		
		function name($name = NULL) { return is_null($name) ? $this->_name : $this->_name = $name; }
		
		function url() { return $this->_url; }
	
		function __call($method, $params) {
			if ($method == __FUNCTION__) return;
			return call_user_func_array(array($this->_handle, $method), $params);
		}
	
		function make_ident() {
			$args = func_get_args();
			$ident = array();
			foreach($args as $arg) {
				$ident[] = $this->quote_ident($arg);
			}
			return implode('.', $ident);
		}
			
		function rewrite(){
			$args=func_get_args();	
			$SQL=array_shift($args);
			foreach($args as $k=>&$v){
				if (is_bool($s) && is_numeric($s)){
				} 
				elseif (is_string($v) && !is_numeric($v)) {
					$v=$this->escape($v);
				} 
				elseif (is_array($v)){
					$v=$this->quote($v);
				}
			}
			return vsprintf($SQL, $args);	
		}
	
		function query() {
			
			$args=func_get_args();
			if (func_num_args()>1) {
				$SQL = call_user_func_array(array($this, 'rewrite'), $args);
			}
			else {
				$SQL=$args[0];
			}
			//去掉不必要的换行符
			$SQL = preg_replace('/[\n\r\t]+/', ' ', $SQL);
		
			if (_CONF('debug.database')?:FALSE) { 
				Log::add($SQL, 'database');
			}
				
			self::$query_count++;
	
			return $this->_handle->query($SQL);
		}
	
		function value() {
			$args=func_get_args();
			$result = call_user_func_array(array($this,'query'), $args);
			return $result ? $result->value():NULL;
		}
		
		private $_trans_in_progress = FALSE;
		function begin_transaction() {
			$this->_handle->begin_transaction();
			$this->_trans_in_progress = TRUE;
	
			return $this;
		}
		
		function commit() {
			if ($this->_trans_in_progress) {
				$this->_handle->commit();
				$this->_trans_in_progress = FALSE;
			}
	
			return $this;
		}
		
		function rollback() {
			if ($this->_trans_in_progress) {
				$this->_handle->rollback();
				$this->_trans_in_progress = FALSE;
			}
	
			return $this;
		}
		
		function snapshot($filename, $tables = NULL) {
	
			if (is_string($tables)) $tables = array($tables);
			else $tables = (array)$tables;
			
			return $this->_handle->snapshot($filename, $tables);
		}
		
		function create_table() {
			$tables = func_get_args();
			foreach($tables as $table) {
				list($table, $engine) = explode(':', $table, 2);
				if (!$this->table_exists($table)) {
					$this->_handle->create_table($table, $engine);
				}			
			}
		}
	
		function drop_table() {
			$tables = func_get_args();
			foreach($tables as $table) {
				$this->_handle->drop_table($table);
			}
		}
	
		function restore($filename, &$retore_filename=NULL, $tables=NULL) {
			$retore_filename = $filename.'.restore'.uniqid();
			if (!$this->snapshot($retore_filename)) return FALSE;
			
			if (is_string($tables)) $tables = array($tables);
			else $tables = (array) $tables;
	
			if (count($tables) > 0) {
				call_user_func_array(array($this, 'drop_table'), $tables);
			}
			else {
				$this->empty_database();
			}
			
			return $this->_handle->restore($filename, $tables);
		}
		
	}
	
}