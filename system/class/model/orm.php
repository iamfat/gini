<?php

/*
$object = new \ORM\Object($id);
*/

namespace Model {

	use \Model\Event;
	use \Model\Database;

	abstract class ORM {

		const RELA_PREFIX = '_r_';
		const PROP_PREFIX = '_p_';
		const JSON_SUFFIX = '_json';

		private static $_injections;
		private $_criteria;
		private $_objects;
		private $_name;
		private $_oinfo;
		
		private $_db_data;
		private $_db_time;	//上次数据库同步的时间

		protected $_uuid;

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
			return call_user_func_array('\Model\Event::'.$func, $args);		
		}

		function inheritance() {
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

		private static $_structures;
		function structure() {

			$class_name = get_class($this);
			if (!isset(self::$_structures[$class_name])) {
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

				foreach ($structure as $k => $v) {
					$params = explode(',', strtolower($v));
					$v = array();
					foreach($params as $p) {
						list($p, $pv) = explode(':', trim($p), 2);
						$v[$p] = $pv;
					}

					$structure[$k] = $v;
				}

				self::$_structures[$class_name] = $structure;
			}

			return self::$_structures[$class_name];
		}

		function __construct($criteria = NULL) {

			// 为每个实例化出来的对象赋一个唯一id, 方便事后判断
			$this->_uuid = uniqid();

			$structure = $this->structure();
			foreach ($structure as $k => $v) {
				unset($this->$k);	//empty all public properties
			}

			if ($criteria) {

				if (is_scalar($criteria)) {
					$criteria = array('id'=>(int)$criteria);
				}

				$criteria = $this->normalize_criteria($criteria);
				$this->_criteria = $criteria;
				
				$db = $this->db();
				//从数据库中获取该数据
				foreach ($criteria as $k=>$v) {
					$where[] = $db->quote_ident($k) . '=' . $db->quote($v);
				}
				
				$name = $this->name();

				// SELECT * from a JOIN b, c ON b.id=a.id AND c.id = b.id AND b.attr_b='xxx' WHERE a.attr_a = 'xxx'; 
				$SQL = 'SELECT *  FROM '.$db->quote_ident($name) . ' WHERE '.implode(' AND ', $where).' LIMIT 1'; 
				
				$result = $db->query($SQL);
				//只取第一条记录
				if ($result) {
					$data = $result->row('assoc');
				}
					
			}

			//给object赋值
			$this->_db_data = (array) $data;
			$this->_db_time = time();
			$this->set_data((array) $data);
		}

		function db() {
			$rc = new \ReflectionClass($this);
			$db_name = $rc->getStaticPropertyValue('_db');
			
			return Database::db($db_name);
		}

		function normalize_criteria(array $crit) {
			$ncrit = array();
			$structure = $this->structure();

			foreach ($crit as $k => $v) {
				if (is_scalar($v) || is_null($v)) {
					$ncrit[$k] = $v;
				}
				elseif ($v instanceof \ORM\Object) {
					if (!isset($structure[$k]['object'])) {
						$ncrit[$k.'_name'] = $v->name();
					}
					$ncrit[$k.'_id'] = $v->id;
				}
			}
			
			return $ncrit;
		}

		function criteria() {
			return $this->_criteria;
		}

		function schema() {
			
			$structure = $this->structure();

			$fields;
			$indexes;

			foreach($structure as $k => $v) {

				$field = NULL;
				$index = NULL;

				foreach($v as $p => $pv) {
					switch ($p) {
					case 'int':
					case 'bigint':
					case 'double':
					case 'datetime':
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
					case 'array':
						$field['type'] = 'text';
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
					case 'serial':
						$field['serial'] = TRUE;
						break;
					case 'index':
						$indexes['_IDX_'.$k] = array('fields'=>array($k));
					case 'object':
						// 需要添加新的$field
						if (!$pv) {
							$fields[$k.'_name'] = array(
								'type' => 'varchar(40)',
							);
						}
						$fields[$k.'_id'] = array(
								'type' => 'bigint'
							);
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
		
		function sync() {

			$schema = (array) $this->schema();

			$db = $this->db();

			$db->begin_transaction();
			
			$success = false;

			$structure = $this->structure();

			$db_data = array();
			foreach ($structure as $k => $v) {
				if (array_key_exists('object', $v)) {
					$oname = $v['object'];
					$o = $this->$k;
					if (!isset($oname)) {
						$db_data[$k.'_name'] = $oname ?: 'object';
					}
					$db_data[$k.'_id'] = $o->id ?: 0;
				}
				elseif (array_key_exists('array', $v)) {
					$db_data[$k] = isset($this->$k)
						? json_encode($this->$k, TRUE) 
						: ( array_key_exists('null', $v) ? 'NULL' : '{}' );
				}
				else {
					$db_data[$k] = $this->$k;
					if (is_null($db_data[$k]) && !array_key_exists('null', $v)) {
						$default = $v['default'];
						if (is_null($default)) {
							if (isset($v['string'])) {
								$default = '';
							}
							elseif (isset($v['datetime'])) {
								$default = '0000-00-00 00:00:00';
							}
							else {
								$default = 0;
							}
						}
						$db_data[$k] = $default;
					}
				}
			}

			// diff db_data and this->_db_data
			$db_data = array_diff_assoc((array)$db_data, (array)$this->_db_data);

			$tbl_name = $this->name();
			$id = (int) ($this->_db_data['id'] ?: $db_data['id']);
			unset($db_data['id']);

			if ($id > 0) {
				$SQL.=' UPDATE ';

				foreach($db_data as $k=>$v){
					$pair[] = $db->quote_ident($k).'='.$db->quote($v);
				}

				$SQL = 'UPDATE '.$db->quote_ident($tbl_name).' SET '.implode(',', $pair).' WHERE '.$db->quote_ident('id').'='.$db->quote($id);
			}
			else {

				$keys = array_keys($db_data);
				$vals = array_values($db_data);
				
				$SQL = 'INSERT INTO '.$db->quote_ident($tbl_name).' ('.$db->quote_ident($keys).') VALUES('.$db->quote($vals).')';
			}

			$success = $db->query($SQL);
			if ($success) {
				if (!$id) {
					$id = $db->insert_id();
				}
				
				$db->commit();

				// SELECT * from a JOIN b, c ON b.id=a.id AND c.id = b.id AND b.attr_b='xxx' WHERE a.attr_a = 'xxx'; 
				$SQL = 'SELECT *  FROM '.$db->quote_ident($tbl_name).' WHERE '.$db->quote_ident('id').'='.$db->quote($id).' LIMIT 1'; 
				
				$result = $db->query($SQL);
				if ($result) {
					$db_data = $result->row('assoc');
				}
					
				//给object赋值
				$this->_db_data = (array) $db_data;
				$this->_db_time = time();
				$this->set_data($this->_db_data);
			}
			else {
				$db->rollback();
			}

			return $success;
		}

		static function inject($injection) {
			self::$_injections[] = $injection;
			// clear structure cache
			unset(self::$_structures[get_called_class()]);
		}

		function name() {
			if (!isset($this->_name)) {
				$this->_name = strtolower(basename(str_replace('\\', '/', get_class($this))));
			}
			return $this->_name;
		}

		function set_data($data) {
			foreach ($this->structure() as $k => $v) {
				if (array_key_exists('object', $v)) {
					$oname = $v['object'];
					$o = $data[$k];
					if (isset($o) && $o instanceof \ORM\Object && isset($oname) && $o->name() == $oname) {
						$this->$k = $o;
					}
					else {
						//object need to be bind later to avoid deadlock.
						unset($this->$k);
						if (!isset($oname)) $oname = strval($data[$k.'_name']);
						if ($oname) {
							$oi = (object) array(
								'name' => $oname,
								'id' => $data[$k.'_id']
							);
							$this->_oinfo[$k] = $oi;
						}
					}
				}
				elseif (array_key_exists('array', $v)) {
					$this->$k = @json_decode(strval($data[$k]), TRUE);				
				}
				else {
					$this->$k = $data[$k];
				}
			}
		}

		function get_data() {
			foreach($this->structure() as $k => $v) {
				$data[$k] = $this->$k;
			}
			return $data;
		}

		function __get($name) {
			if (isset($this->_objects[$name])) {
				return $this->_objects[$name];
			}
			elseif (isset($this->_oinfo[$name])) {
				$oi = $this->_oinfo[$name];
				$oclass = '\\ORM\\'.$oi->name;
				$o = new $oclass($oi->id);
				$this->_objects[$name] = $o;
				return $o;
			}
			elseif (isset($this->_extra[$name])) {
				// try find it in _extra
				return $this->_extra[$name];
			}
		}

		function __set($name, $value) {		
			$structure = $this->structure();
			if (isset($this->_oinfo[$name])) {
				$this->_objects[$name] = $value;
			}
			elseif (isset($structure[$name])) {
				$this->$name = $value;
			}
			else {
				$this->_extra[$name] = $value;
			}
		}

	}	
}

