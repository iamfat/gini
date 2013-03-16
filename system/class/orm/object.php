<?php


namespace ORM;

class Object extends \Model\ORM {

	var $id = 'bigint,primary,serial';
	var $_extra = 'array';

	static $_db;	// database object
	static $_db_index ; // database index,

	private $those;	// add to support those API

	function fetch($force=FALSE) {
		if ($this->criteria() === NULL && $this->those) {
			//try those API
			$ids = (array) $this->those->limit(1)->get('id');
			$this->criteria(reset($ids));
		}
		return parent::fetch($force);
	}

	function __call($method, $params) {
		if ($method == __CLASS__) return;
		
		if (method_exists('\\Model\\Those', $method)) {
			if (!$this->those) $this->those = new \Model\Those($this->name());
			call_user_func_array(array($this->those, $method), $params);
			return $this;
		}
		
		return call_user_func_array(array($this->object, $method), $params);
	}

}


