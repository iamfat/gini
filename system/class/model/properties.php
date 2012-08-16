<?php

abstract class _Properties {
	
	private $_items;
	private $_object;
	
	private $_updated=FALSE;

	private static $_cache=array();
		
	static function setup(){}
	
	function __construct($object){
		
		$this->_object = $object;

		$name = $object->name();
		$id = $object->id;
		
		$db = ORM_Model::db($name);
		$table = ORM_Model::PROP_PREFIX.$name;

		$code = $db->value('SELECT `data` FROM `%s` WHERE `id`=%d', $table, $id);

		//$data = @unserialize(@base64_decode($code)?:$code);
		// TO BE REMOVED: 这两种形式 哪种兼容性更好一些? Jia Huang @ 2010.12.19
		//
		$data = (array) (@unserialize($code) ?: @unserialize(base64_decode($code)));

		$this->_items = $data;
	}
	
	function & __get($name) {
		return $this->get($name);
	}
	
	function __set($name, $value){
		$this->set($name, $value);
	}
	
	function get($name) {
		if(isset($this->_items[$name])) return $this->_items[$name];
		return NULL;
	}

	function data()
	{
		/* NO.BUG #190 (xiaopei.li@2010.11.26) */
		return $this->_items;
	}
	
	function set($name, $value=NULL) {
		if (is_array($name)) {
			array_map(array($this, __FUNCTION__), array_keys($name), array_values($name));
		} else {
			if($value===NULL){
				unset($this->_items[$name]);
			}else{
				$this->_items[$name] = $value;
			}
			$this->_updated = TRUE;
		}
		return $this;
	}
	
	function delete() {
		$name = $this->_object->name();
		$db = ORM_Model::db($name);
		$table=ORM_Model::PROP_PREFIX.$name;
		$id = $this->_object->id;
		$db->query('DELETE FROM `%s` WHERE `id`=%d', $table, $id);
		return $this;
	}
	
	function save(){
		if($this->_updated){
			$data = @serialize($this->_items);
			$name = $this->_object->name();
			$db = ORM_Model::db($name);
			$table=ORM_Model::PROP_PREFIX.$name;
			$db->prepare_table($table, 
				array(
					'fields' => array(
						'id'=>array('type'=>'bigint', 'null'=>FALSE, 'default' => 0),
						'data'=>array('type'=>'blob', 'null'=>TRUE),
					), 
					'indexes' => array( 
						'PRIMARY'=>array('type'=>'primary', 'fields'=>array('id')),
					)
				)
			);

			$id = $this->_object->id;
			$db->query('INSERT INTO `%1$s` VALUES (%2$d, "%3$s") ON DUPLICATE KEY UPDATE `data`="%3$s"', $table, $id, base64_encode($data));

			$this->_updated = FALSE;
			
		}
		return $this;
	}
	
	static function factory($object) {
		
		if (!($object instanceof ORM_Model) || !$object->id) {
			throw new Error_Exception(T('无法识别的对象!'));
		}
		
		if (PHP_SAPI == 'cli') return new Properties($object);

		$key = (string) $object;
		if (!self::$_cache[$key]) {
			self::$_cache[$key] = new Properties($object);
		}
		
		return self::$_cache[$key];
	}

	function object() {
		return $this->_object;
	}

}

function P($object) {
	return Properties::factory($object);
}
