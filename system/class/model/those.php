<?php

/*

$user = those('user')
			->whose('id')->is_in(1, 2, 3)
			->or_whose('atime')->is_greater_than(3)
			->and_whose('age')->is_between(5, 15);

$user = those('user')
			->who_is('')

$user = those('user')
			->alias('father')
			->whose('friend')->is_in(
				those('user')->whose('parent_name')->is('@father.name')
			);

*/

namespace Model\Those {

	class Node {

		public $db;	
		public $name;
		public $table;
		
		public $SQL;
		public $count_SQL;
		public $from_SQL;

		public $join;
		public $where;
		public $limit;
		public $order_by;

		public $join_criteria = array();
		public $join_tables = array();

		public $rels = array();

		private $or_query;
		private $and_query;

		private $prev_or_query;
		private $prev_and_query;

		private static $_uniqid = 0;
		function uniqid() {
			return (self::$_uniqid++);
		}

		function __construct($name, $db) {
			$this->db = $db;
			$this->name = $name;
			$this->table = 't'.$this->uniqid();
		}

		function alias($name) {
			$this->alias[$name] = $this->table;
		}

		function is_in(array $values) {
			$db = $this->db;
			$field_name = $db->make_ident($this->table, $this->field);

			$v = reset($values);
			if ($v instanceof Node) {
				$this->join[] = 'INNER JOIN '.$db->make_ident($this->name).' AS '.$db->quote_ident($this->table) 
						. ' ON ' . $field_name . '=' . $db->make_ident($v->table, 'id');
				if ($v->join) {
					$this->join = array_merge($this->join, $v->join);
				}
				if ($v->where) {
					$this->where = array_merge($this->where, $v->where);
				}
			}
			else {
				foreach ($values as $v) {
					$qv[] = $db->quote($v);
				}
				$this->where[] = $field_name . ' IN (' . implode(', ', $qv) .')';
			}
		}

		function is_not_in(array $values) {
			$db = $this->db;
			$field_name = $db->make_ident($this->table, $this->field);
			
			$v = reset($values);
			if ($v instanceof Node) {
				$this->join[] = 'LEFT JOIN '.$db->make_ident($this->name).' AS '.$db->quote_ident($this->table)
						. ' ON ' . $field_name . '=' . $db->make_ident($v->table, 'id');
				if ($v->join) {
					$this->join = array_merge($this->join, $v->join);
				}
				if ($v->where) {
					$this->where = array_merge($this->where, $v->where);
					$this->where[] = 'AND';
				}
				$this->where[] = $field_name . ' IS NOT NULL';
			}
			else {
				foreach ($values as $v) {
					$qv[] = $db->quote($v);
				}
				$this->where[] = $field_name . ' NOT IN (' . implode(', ', $qv) .')';
			}
		}

		function get_value($value) {
			if (preg_match('/^@(?:(\w+)\.)?(\w+)$/', $value, $parts)) {
				//有可能是某个table的field名
				list(, $table, $field) = $parts;
				if ($table) {
					while (isset($this->alias[$table])) {
						$table = $this->alias[$table];
					}
				}
				else {
					$table = $this->table;
				}
				return $this->db->make_ident($table, $field);
			}
			return $this->db->quote($value);
		}
		
		public function pack_where($where, $op = 'AND') {
			if (!is_array($where)) $where = array($where);
			if (count($where) <= 1) return $where[0];
			return '('.implode( ' '.$op.' ', $where).')'; 
		}

		function between($a, $b) {
			assert($this->field);
			$db = $this->db;
			$field_name = $db->make_ident($this->table, $this->field);
			$this->where[] = '(' . $field_name . '>=' . $this->get_value($a) . 
							 ' AND ' . $field_name . '<' . $this->get_value($b) . ')';
		}

		function match($op, $value) {

			assert($this->field);

			$db = $this->db;
			$field_name = $db->make_ident($this->table, $this->field);

			switch($op) {
				case '^=': {
					$this->where[] = $field_name .' LIKE "'.$db->escape($value).'%%"';
				}
				break;

				case '$=': {
					$this->where[] = $field_name .' LIKE "%%'.$db->escape($value).'"';
				}
				break;

				case '*=': {
					$this->where[] = $field_name .' LIKE "%%'.$db->escape($value).'%%"';
				}
				break;

				case '=': case '!=': {					
					if ($value instanceof \ORM\Object) {
						$class_name = '\\ORM\\'.ucwords($this->name);
						$o = new $class_name;
						$field = $this->field;
						$structure = $o->structure();
						if (array_key_exists('object', $structure[$field])) {
							if (!$structure[$field]['object']) {
								$obj_where[] = $db->make_ident($this->table, $field . '_name') . $op . $db->quote($value->name());

							}

							$obj_where[] = $db->make_ident($this->table, $field . '_id') . $op . intval($value->id);

							if ($op == '!=') {
								$this->where[] = $this->pack_where($obj_where, 'OR');
							}
							else {
								$this->where[] = $this->pack_where($obj_where, 'AND');
							}
							break;
						}
					}
				}
				default: {
					$this->where[] = $field_name . $op . $this->get_value($value);
				}

			}

		}

		function order_by($field, $mode) {
			$db = $this->db;
			$mode = strtolower($mode);
			switch ($mode) {
			case 'desc':
			case 'd':
				$this->order_by[] =$db->make_ident($this->table, $field) . ' DESC';
				break;
			case 'asc':
			case 'a':
				$this->order_by[] = $db->make_ident($this->table, $field) . ' ASC';
				break;
			}
		}

		function finish() {

			$db = $this->db;
			$table = $this->table;

			$from_SQL = 'FROM ' . $db->make_ident($this->name).' AS '.$db->quote_ident($this->table);

			if ($this->where) {
				$from_SQL .= ' WHERE ' . implode(' ', $this->where);
			}

			$this->from_SQL = $from_SQL;

			if ($this->order_by) {
				$order_SQL = 'ORDER BY ' . implode(', ', $this->order_by);
			}

			if ($this->limit) {
				$limit_SQL = 'LIMIT ' . $this->limit;
			}

			$id_col = $db->make_ident($table, 'id');
			$this->SQL = "SELECT DISTINCT $id_col $from_SQL $order_SQL $limit_SQL";
			$this->count_SQL = "SELECT COUNT(DISTINCT $id_col) AS `count` $from_SQL";

		}

	}

}

namespace Model {

	class Those extends ORM_Iterator {

		public $node;

		static function setup() {

		}

		function __construct($name) {
			parent::__construct($name);
			$this->node = new Those\Node($name, $this->db);
		}

		function __clone() {
			parent::__clone();
			$this->node = clone $this->node;
		}

		protected function fetch($scope='fetch') {
			if (!$this->SQL) {
				$this->node->finish();
				$this->SQL = $this->node->SQL;
				$this->count_SQL = $this->node->count_SQL;
			}
			return parent::fetch($scope);			
		}

		function limit($start, $per_page = NULL) {

			$those = clone $this;
			$those->set_fetch_flag('*', FALSE);
			$those->SQL = NULL;
			$those->count_SQL = NULL;

			if ($per_page > 0) {
				$those->node->limit = sprintf("%d, %d", $start, $per_page);
			}
			else {
				$those->node->limit = sprintf("%d", $start);
			}

			return $those;
		}

		function and_whose($field) {
			$this->node->where[] = 'AND';
			$this->node->field = $field;
			return $this;
		}

		function or_whose($field) {
			$this->node->where[] = 'OR';
			$this->node->field = $field;
			return $this;
		}

		function whose($field) {
			assert(!$this->node->where);
			$this->node->field = $field;
			return $this;
		}

		function who_is($field) {
			return $this;
		}

		function which_is($field) {
			return $this->who_is($field);
		}

		function and_who_is($field) {
			return $this;
		}

		function and_which_is($field) {
			return $this->and_who_is($field);
		}

		function or_who_is($field) {
			return $this;
		}

		function or_which_is($field) {
			return $this->and_who_is($field);
		}

		function of($those) {
			return $this;
		}

		function alias($name) {
			$this->node->alias($name);
			return $this;
		}
			
		function is_in() {
			$args = func_get_args();
			$this->node->is_in($args);
			return $this;
		}

		function is_not_in() {
			$args = func_get_args();
			$this->node->is_not_in($args);
			return $this;
		}

		function match($op, $v) {
			$this->node->match($op, $v);
			return $this;
		}

		// is(1), is('hello'), is('@name')
		function is($v) {
			return $this->match('=', $v);
		}

		function is_not($v) {
			return $this->match('!=', $v);
		}

		function begins_with($v) {
			return $this->match('^=', $v);
		}

		function contains($v) {
			return $this->match('*=', $v);
		}

		function ends_with($v) {
			return $this->match('$=', $v);
		}

		function is_less_than($v) {
			return $this->match('<', $v);
		}

		function is_greater_than($v) {
			return $this->match('>', $v);
		}

		function is_greater_than_or_equal($v) {
			return $this->match('>=', $v);
		}

		function is_less_than_or_equal($v) {
			return $this->match('<=', $v);
		}

		function is_between($a, $b) {
			$this->node->between($a, $b);
			return $this;
		}

		function sort_by($field, $mode='asc') {
			$this->node->order_by($field, $mode);
			return $this;
		}
	}

}

namespace {

	function those($name) {
		return new \Model\Those($name);
	}

}