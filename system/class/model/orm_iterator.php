<?php

namespace Model {

	abstract class ORM_Iterator implements Iterator, ArrayAccess, Countable {

		protected $db;

		protected $name;
		
		protected $current_id;
		protected $objects = array();
		
		protected $count;		//符合selector的数据总数
		protected $length;	//实际获得数据数
		
		protected $SQL;
		protected $count_SQL;

		function total_count() {
			$this->check_query('count');
			return (int) $this->count;
		}

		function length(){return $this->count();}
		function name(){return $this->name;}

		function __construct($name, $SQL, $count_SQL=NULL, $db=NULL){
		
			$this->name = $name;
			$this->SQL = $SQL;
			$this->db = $db instanceof Database ? $db : Database::factory($db);

			if (!$count_SQL) {
				$count_SQL = preg_replace('/\bSQL_CALC_FOUND_ROWS\b/', '', $SQL);
				$count_SQL = preg_replace('/^(SELECT)\s(.+?)\s(FROM)\s/', '$1 COUNT($2) count $3', $count_SQL);
				$count_SQL = preg_replace('/ COUNT\((.+?)\.\*\) count/', ' COUNT($1.id) count', $count_SQL);
				$count_SQL = preg_replace('/\sORDER BY.+$/', '', $count_SQL);
				$count_SQL = preg_replace('/\sLIMIT.+$/', '', $count_SQL);
			}

			$this->count_SQL = $count_SQL;
			
			$this->check_query();
		}

		private $_query_flag;
		protected function set_query($scope, $enable=TRUE) {
			if ($enable) {
				$this->_query_flag[$scope] = TRUE;
			}
			else {
				unset($this->_query_flag[$scope]);
			}
		}

		protected function isset_query($scope) {
			return isset($this->_query_flag['*']) || isset($this->_query_flag[$scope]);
		}

		protected function check_query($scope='fetch') {
			if ($this->isset_query($scope)) return $this;

			switch($scope) {
			case 'count':
				$this->count = $this->count_SQL ? $this->db->value($this->count_SQL) : 0;
				break;
			default:
				if ($this->SQL) {
					$result = $this->db->query($this->SQL);

					$objects = array();

					if ($result) {
						while ($row=$result->row('assoc')) {
							$objects[$row['id']] = O($this->name, $row['id']);
						}
					}

					$this->objects = $objects;
					$this->length = count($objects);
					$this->current_id = key($objects);
				}
			}

			$this->set_query($scope, TRUE);

			return $this;
		}

		function delete_all() {
			$this->check_query();
			foreach ($this->objects as $object) {
				if (!$object->delete()) return FALSE;
			}
			return TRUE;
		}

		// Iterator Start
		function rewind(){
			$this->check_query();
			reset($this->objects);
			$this->current_id = key($this->objects);
		}
		
		function current(){ 
			$this->check_query();
			return $this->objects[$this->current_id]; 
		}
		
		function key(){
			$this->check_query();
			return $this->current_id;
		}
		
		function next(){
			$this->check_query();
			next($this->objects);
			$this->current_id = key($this->objects);
			return $this->objects[$this->current_id];
		}
		
		function valid(){
			$this->check_query();
			return isset($this->objects[$this->current_id]);
		}
		// Iterator End

		// Countable Start
		function count(){
			$this->check_query();
			return (int) $this->length;
		}
		// Countable End
		
		// ArrayAccess Start
		function offsetGet($id){
			$this->check_query();
			if($this->length>0){
				return $this->objects[$id];
			}
			return NULL;
		}
		
		function offsetUnset($id){
			$this->check_query();
			unset($this->objects[$id]);
			$this->count = $this->length = count($this->objects);
			if ($this->current_id==$id) $this->current_id = key($this->objects);
		}
		
		function offsetSet($id, $object){
			$this->check_query();
			$object->id=$id;
			$this->objects[$id]=$object;
			$this->count = $this->length = count($this->objects);
			$this->current_id=$id;
		}
		
		function offsetExists($id){
			$this->check_query();
			return isset($this->objects[$id]);
		}

		// ArrayAccess End

		function prepend($object){
			$this->check_query();

			if(is_array($object)){
				$object = O($this->name, $object, TRUE);
			}
			elseif ($object instanceof ORM_Iterator) {
				$object = $object->objects[$object->current_id];
			}
			
			if ($object instanceof ORM_Model && $object->id) {
				$this->objects = array($object->id => $object) + $this->objects;
			}
			
			$this->count = $this->length = count($this->objects);
			return $this;
		}

		function append($object){
			$this->check_query();

			if (is_array($object)) {
				$object = O($this->name, $object, TRUE);
			}
			elseif ($object instanceof ORM_Iterator) {
				$object = $object->objects[$object->current_id];
			}
			
			if ($object instanceof ORM_Model && $object->id) {
				$this->objects[$object->id] = $object;
			}
			
			$this->count = $this->length = count($this->objects);
			return $this;
		}
		
		function __toString() {
			return $this->name.'::__toString()';
		}
		
		function reverse() {
			$this->check_query();
			//反排
			$this->objects = array_reverse($this->objects);
			return $this;
		}

		function & to_assoc($key_name = 'id', $val_name = 'name') {
			$this->check_query();
			$assoc = array();
			foreach($this->objects as $o) {
				$assoc[$o->$key_name] = $o->$val_name;
			}
			return $assoc;
		}
		
	}

}
