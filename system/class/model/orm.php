<?php

namespace Model;

/*

$object = new \Model\ORM\Object($id);
$object = O('user', 1);
*/

use \Gini\Event;
use \Model\Database;

abstract class ORM {

	const RELA_PREFIX = '_r_';
	const PROP_PREFIX = '_p_';
	const OBJ_NAME_SUFFIX = '_name';
	const OBJ_ID_SUFFIX = '_id';
	const JSON_SUFFIX = '_json';

	private static $_injections;

	function __call($method, $params) {
		if ($method == __FUNCTION__) return;
		/*
		orm[user].call[method]
		*/
		$name = "call[$method]";
		if (!$this->event('is_binded', $name)) {
			$trace = debug_backtrace();
			$message = sprintf("Framework Error: Call to undefined method %s::%s() in %s on line %d", 
								$trace[1]['class'], 
								$trace[1]['function'],
								$trace[1]['file'],
								$trace[1]['line']);
			trigger_error($message, E_USER_ERROR);
			return;
		}
		
		return $this->event('trigger', $name, $params);
	}

	private function event() {

		$args = func_get_args();
		$func = array_shift($args);
		$action = array_shift($args);

		$inheritance = $this->inheritance();

		$events = array();
		foreach(array_keys($inheritance) as $name) {
			$events[] = "orm[$name].$action";
		}

		array_unshift($args, implode(' ', $events), $this);
		return call_user_func_array('\Gini\Event::'.$func, $args);		
	}

	private function inheritance() {
		$inheritance = array();

		$rc = new \ReflectionClass($this);
		while ($rc) {
			$name = strtolower($rc->getShortName());
			$inheritance[$name] = $rc->getName();
			if ($name == 'object') break;
			$rc = $rc->getParentClass();
		}

		return $inheritance;	
	}

	private function structure() {
		$rc = new \ReflectionClass($this);
		$defaults = $rc->getDefaultProperties();

		$structure = array();
		foreach($rc->getProperties() as $p) {
			if (!$p->isStatic() && $p->isPublic()) {
				$k = $p->getName();
				$structure[$k] = $defaults[$k];
			}
		}

		//check all injections
		foreach ((array) self::$_injections as $injection) {
			$rc = new \ReflectionClass($injection);
			$defaults = $rc->getDefaultProperties();

			foreach($rc->getProperties() as $p) {
				if (!$p->isStatic() && $p->isPublic()) {
					$k = $p->getName();
					$structure[$k] = $defaults[$k];
				}
			}
		}

		return $structure;
	}

	function db() {
		$rc = new \ReflectionClass($this);
		$db_name = $rc->getStaticPropertyValue('_db');
		
		return Database::db($db_name);
	}

	function __construct($criteria = NULL) {

		$structure = $this->structure();
		foreach ($structure as $k => $v) {
			$this->$k = NULL;	//empty all public properties
		}

		if (!$criteria) return;

		$inheritance = $this->inheritance();
		if (is_scalar($criteria)) {
			$criteria = array('id'=>$criteria);
		}

		$db = $this->db();
die;
		$object->encode_objects($criteria);

		//从数据库中获取该数据
		foreach ($criteria as $k=>$v) {
			$where[] = $db->quote_ident($k) . '=' . $db->quote($v);
		}
		
		// SELECT * from a JOIN b, c ON b.id=a.id AND c.id = b.id AND b.attr_b='xxx' WHERE a.attr_a = 'xxx'; 
		$SQL = 'SELECT * FROM '.$db->quote_ident($real_name).' WHERE '.implode(' AND ', $where).' LIMIT 1'; 
		
		$result = $db->query($SQL);
		//只取第一条记录
		if ($result) {
			$data = (array) $result->row('assoc');
		}
		else {
			$data = array();
		}
				
		$delete_me = FALSE;

		if ($data['id']) {
			$id = $data['id'];
		}
		
		if ($id && count($real_names) > 0) {

			foreach ($real_names as $rname) {
				
				$db = self::db($rname);
				$result = $db->query('SELECT * FROM `%s` WHERE `id`=%d', $rname, $id);
				$d = $result ? $result->row('assoc') : NULL;
				if ($d !== NULL) {
					$data += $d;
				}
				else {
					// 父类数据不存在
					$delete_me = TRUE;
					$delete_me_until = $rname;	//删除到该父类
					break;
				}
			}
			
			if ($delete_me) {
				// 如果父类数据不存在 删除相关数据
				foreach ($real_names as $rname) {
					if ($delete_me_until == $rname) break;
					$db = self::db($rname);
					$db->query('DELETE FROM `%s` WHERE `id`=%d', $rname, $id);
				}
				
				$data = array();
			}

		}

		//给object赋值
		$object->set_data($data);

		
	}

	function schema() {
		
		$structure = $this->structure();

		$fields;
		$indexes;

		foreach($structure as $k => $v) {

			$field = NULL;
			$index = NULL;

			$params = explode(',', strtolower($v));
			foreach($params as $p) {
				list($p, $pv) = explode(':', trim($p), 2);
				switch ($p) {
				case 'int':
				case 'bigint':
				case 'double':
				case 'date':
					$field['type'] = $p;
					break;
				case 'bool':
					$field['type'] = 'int';
					break;
				case 'string':
					if ($pv == '*') {
						$field['type'] = 'text';
					}
					else {
						$field['type'] = 'varchar('.($pv?:255).')';
					}
					break;
				case 'null':
					$field['null'] = TRUE;
					break;
				case 'default':
					$field['default'] = $pv;
					break;
				case 'primary':
					$indexes['PRIMARY'] = array('type' => 'primary', 'fields'=> array($k));
					break;
				case 'unique':
					$indexes['_IDX_'.$k] = array('type' => 'unique', 'fields'=> array($k));
					break;
				case 'auto_increment':
					$field['auto_increment'] = TRUE;
					break;
				case 'index':
					$indexes['_IDX_'.$k] = array('fields'=>array($k));
				}

			}

			if ($field) $fields[$k] = $field;

		}

		$ro = new \ReflectionObject($this);
		$static_props = $ro->getStaticProperties();

		$db_index = $static_props['db_index'];
		if (count($db_index) > 0) {
			// 索引项
			foreach ($db_index as $k => $v) {

				list($vk, $vv) = explode(':', $v, 2);
				$vk = trim($vk);
				$vv = trim($vv);
				if (!$vv) {
					$vv = trim($vk); $vk = NULL;
				}

				$vv = explode(',', $vv);
				foreach ($vv as &$vvv) {
					$vvv = trim($vvv);
				}

				switch($vk) {
				case 'unique':
					$indexes['_MIDX_'.$k] = array('type' => 'unique', 'fields'=>$vv);
					break;
				case 'primary':
					$indexes['PRIMARY'] = array('type' => 'primary', 'fields'=>$vv);
					break;
				default:
					$indexes['_MIDX_'.$k] = array('type' => 'unique', 'fields'=>$vv);
				}

			}
		}

		return array('fields' => $fields, 'indexes' => $indexes);
	}
	
	function save($overwrite=TRUE) {

		$db = $this->db();

		$properties = $this->properties();
		foreach($properties as $k => $v) {

		}

		$db->begin_transaction();

		$db->commit();
	}

	static function inject($injection) {
		self::$_injections[] = $injection;
	}
	
}

