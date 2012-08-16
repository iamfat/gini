<?php

interface Q_Pseudo {
	function process($part);
}

class Q_Query {

	const RELA_PREFIX = '_r_';
	const OBJ_NAME_SUFFIX = '_name';
	const OBJ_ID_SUFFIX = '_id';

	private $stack = array();

	public $db;	
	public $name;
	public $table;
	public $SQL;
	public $count_SQL;
	public $from_SQL;

	public $prev_name;
	public $prev_table;

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

	private static $storable = array('prev_name', 'prev_table', 'where', 'rels', 'join_tables', 'join_criteria', 'alias', 'table_name');

	function fields($name) {
		return ORM_Model::fields($name);
	}

	function schema($name) {
		return ORM_Model::schema($name);
	}

	function counterpart($name) {
		return ORM_Model::counterpart($name);
	}

	function real_name($name) {
		return ORM_Model::real_name($name)?:$name;
	}

	function store(& $arr = FALSE) {
		$vars = array();
		foreach (self::$storable as $key) {
			$vars[$key] = $this->$key;
		}
		if ($arr === FALSE) $arr = & $this->stack;
		$arr[] = $vars;
	}

	function log() {
		if (defined('DEBUG_Q')) {
			$args = func_get_args();
			echo call_user_func_array('vsprintf', $args)."\n";
		}
	}

	function & restore($pop = TRUE, & $arr = FALSE) {
		$this->log('RESTORE %d', $pop);
		if ($arr === FALSE) $arr = & $this->stack;
		if ($pop) {
			$vars = array_pop($arr);
		}
		else {
			$vars = end($arr);
		}
		if (NULL === $vars) return FALSE;
		foreach (self::$storable as $key) {
			$this->$key = $vars[$key];
		}
		return TRUE;
	}

	private function finalize_join($join_type=NULL) {
		$this->log('FINALIZE_JOIN %s', $join_type);
		if (count($this->join_tables) > 0) {

			switch($join_type) {
			case 'left':
				$join_command = 'LEFT JOIN';
				break;
			case 'right':
				$join_command = 'RIGHT JOIN';
				break;
			default:
				$join_command = 'INNER JOIN';
			}

			$this->join[] = $join_command.' ('.implode(', ', array_reverse($this->join_tables)).') ON '.$this->pack_where($this->join_criteria, 'AND');

			$this->join_criteria = array();
			$this->join_tables = array();
		}
	}

	public function pack_where($where, $op = 'AND') {
		if (!is_array($where)) $where = array($where);
		if (count($where) <= 1) return $where[0];
		return '('.implode( ' '.$op.' ', $where).')'; 
	}

	public function parse_id($part) {
		$this->where[]= $this->db->make_ident($this->table, 'id').'='. $this->db->quote($part[1]);
	}

	public function parse_pseudo($parts) {
		$pseudo = $parts[1];
		$pseudo_class = 'Q_Pseudo_'.$pseudo;
		if (class_exists($pseudo_class)) {
			$pseudo = new $pseudo_class($this);
			$pseudo->process($parts[2]);
		}
	}

	const PATTERN_FIELD_EXPRESSION = '/\s*(!?)((?:[\w\pL-\~])+)\s*(?:(\^=|\$=|\*=|!=|<=|>=|<|>|=)\s*(.+?)|)\s*(?:\||&|$)/u';
	const PATTERN_FIELD_VALUE = '/(?:(-?[\w_\d.]*)~(-?[\w_\d.]*)|"((?:[^"]|\\")*)"|([^\s,]+))\s*(?:,\s*|$)/u';
	const PATTERN_FIELD_OBJECT = '/(\w+)#(-?\d+)/';

	private function get_field_value($value, $has_quotes=FALSE) {
		if (!$has_quotes) {
			if (preg_match('/^@(?:(\w+)\.)?(\w+)$/', $value, $parts)) {
				//有可能是某个table的field名
				list($foo, $table, $field) = $parts;
				if ($table) {
					while ($this->alias[$table]) {
						$table = $this->alias[$table];
					}
				}
				else {
					$table = $this->table;
				}
				$name = $this->table_name[$table];
				$fields = $this->fields($name);
				if (isset($fields[$field])) {
					return $this->db->make_ident($table, $field);
				}
			}
		}		
		return $this->db->quote($value);
	}

	public function parse_filter($part) {

		if(!preg_match_all(self::PATTERN_FIELD_EXPRESSION, $part[1], $exps, PREG_SET_ORDER)) return;

		$schema = $this->schema($this->name);
		$db = $this->db;

		$where = array();
		foreach ($exps as $exp) {

			list ($foo, $not, $field, $operator) = $exp;

			if(!$operator) {

				if (isset($schema['object_keys'][$field])) {
					$real_field = $field.self::OBJ_ID_SUFFIX;
				}
				else {
					$real_field = $field;
				}

				$ident = $db->make_ident($this->table, $real_field);

				if ($not) {
					$where[] = "($ident IS NULL OR $ident = '')";
				} else {
					$where[] = "($ident IS NOT NULL AND $ident != '')";
				}

				continue;
			}

			$value = stripcslashes($exp[4]);

			if($operator == '=') {
				list($field1, $field2) = explode('~', $field, 2);
				if ($field2) {
					$where[] = '('.$db->make_ident($this->table, $field1). ' <= ' . $db->quote($value)
						.' AND '
						. $db->make_ident($this->table, $field2). ' >= ' . $db->quote($value)
						.')';
					continue;
				}
			}

			if(preg_match_all(self::PATTERN_FIELD_VALUE, $value, $matches, PREG_SET_ORDER)){
				$sub_where = array();
				foreach($matches as $match){
					if (!isset($match[1])) continue;
					if ($match[3]) $match[4] = $match[3];
					if (isset($match[3])) {
						//单一值
						switch($operator){
						case '^=':
							$sub_where[] = $db->make_ident($this->table, $field).' LIKE "'.$db->escape($match[4]).'%%"';
							break;
						case '$=':
							$sub_where[] = $db->make_ident($this->table, $field).' LIKE "%%'.$db->escape($match[4]).'"';
							break;
						case '*=':
							$sub_where[] = $db->make_ident($this->table, $field).' LIKE "%%'.$db->escape($match[4]).'%%"';
							break;
						case '=': case '!=':
							if (isset($schema['object_keys'][$field])) {
								if(preg_match(self::PATTERN_FIELD_OBJECT, $match[4], $obj_matches)) {
									$val_oname = $obj_matches[1];
									$val_oid = $obj_matches[2] ?: 0;
								}
								else {
									$val_oname = NULL;
									$val_oid = $match[4];
								}
								$obj_where = array();
								if (!isset($schema['object_keys'][$field]['oname'])) {
									$field_name = $field.self::OBJ_NAME_SUFFIX;
									$obj_where[] = $db->make_ident($this->table, $field_name) . $operator . $db->quote($val_oname);
								}
								$field_id = $field.self::OBJ_ID_SUFFIX;
								$obj_where[] = $db->make_ident($this->table, $field_id) . $operator . $db->quote($val_oid);
								if ($operator == '!=') {
									$sub_where[] = $this->pack_where($obj_where, 'OR');
								}
								else {
									$sub_where[] = $this->pack_where($obj_where, 'AND');
								}
								break;
							}
						default:
							$field_value = $this->get_field_value($match[4], !!$match[3]);
							$sub_where[] = $db->make_ident($this->table, $field).$operator.$field_value;
						}
					} else {
						// field = 5~10
						// field != 6~7
						$rng_where = NULL;
						if($match[1]!==''){
							$field_value = $this->get_field_value($match[1]);
							$rng_where[] = $db->make_ident($this->table, $field) . ($operator == '=' ? '>=':'<') . $field_value;
						}
						if($match[2]!==''){
							$field_value = $this->get_field_value($match[2]);
							$rng_where[] = $db->make_ident($this->table, $field) . ($operator == '=' ? '<=':'>') . $field_value;
						}
						$sub_where[] = $this->pack_where($rng_where, 'AND');
					}
				}
				if (count($sub_where)>0) $where[] = $this->pack_where($sub_where, 'OR');
			} 
		}

		if (count($where)>0) $this->where[] = $this->pack_where($where, 'OR');
	}

	public function parse_prel($part) {
		$str = $part[1];
		if ($str[0] == '@') {
			$rels = explode('|', substr($str, 2, -1));
			foreach($rels as $r) {
				$r = trim($r);
				$this->rels[] = array(NULL, $r, TRUE);	//有名关系 a<father b		b.father_id = a
			}
		}
		else {
			$this->rels[] = array(NULL, $str, FALSE);	//有名关系 a<father b		b.father_id = a
		}
	}

	public function parse_rel($part) {
		$str = $part[1];
		if ($str[0] == '@') {
			$rels = explode('|', substr($str, 2, -1));
			foreach($rels as $r) {
				$r = trim($r);
				$this->rels[] = array($r, NULL, TRUE);	//有名关系 a b.father 	a.father_id = b
			}
		}
		else {
			$this->rels[] = array($str, NULL, FALSE);	//有名关系 a b.father		a.father_id = b
		}
	}

	private function & parse_rel_criteria(&$rels, &$nn_table, $nn_flip, &$has_nn_rel) {

		$prev_fields = $this->fields($this->prev_name);
		$fields = $this->fields($this->name);

		$db = $this->db;

		$nn_rels = array();
		$join_criteria = array();

		foreach ($rels as $part) {

			list($name, $prev_name) = $part;

			$oid = $name.self::OBJ_ID_SUFFIX;
			$prev_oid = $prev_name.self::OBJ_ID_SUFFIX;

			if ($prev_name && isset($fields[$prev_oid])) {
				//1:n 当前表中包含与上一个表的表名同名的字段
				$prev_oname = $prev_name.self::OBJ_NAME_SUFFIX;
				if (isset($fields[$prev_oname])) {
					$join_criteria[] = $db->make_ident($this->table, $prev_oname).'='.$db->quote($this->prev_name);
				}
				$join_criteria[] = $db->make_ident($this->table, $prev_oid) .'='. $db->make_ident($this->prev_table, 'id');
			} 
			elseif ($name && isset($prev_fields[$oid])) {
				//n:1 上一个表中包含与当前表同名的字段
				$oname = $name.self::OBJ_NAME_SUFFIX;
				if (isset($prev_fields[$oname])) {
					$join_criteria[] = $db->make_ident($this->prev_table, $oname) . '=' . $db->quote($this->name);
				}
				$join_criteria[] = $db->make_ident($this->prev_table, $oid) . '=' . $db->make_ident($this->table, 'id');
			} 
			elseif (!$name && $prev_name) {
				$name = $this->counterpart($prev_name);
				$oid = $name.self::OBJ_ID_SUFFIX;
				if (isset($prev_fields[$oid])) {
					$oname = $name.self::OBJ_NAME_SUFFIX;
					if (isset($prev_fields[$oname])) {
						$join_criteria[] = $db->make_ident($this->prev_table, $oname) . '=' . $db->quote($this->name);
					}
					$join_criteria[] = $db->make_ident($this->prev_table, $oid) . '=' . $db->make_ident($this->table, 'id');
				}
				else {
					if ($nn_flip) {
						$nn_rels[] = $prev_name;
					}
					else {
						$nn_rels[] = $name;
					}
				}
			}
			elseif ($name && !$prev_name) {
				$prev_name = $this->counterpart($name);
				$prev_oid = $prev_name.self::OBJ_ID_SUFFIX;
				if (isset($fields[$prev_oid])) {
					$prev_oname = $prev_name.self::OBJ_NAME_SUFFIX;
					if (isset($fields[$prev_oname])) {
						$join_criteria[] = $db->make_ident($this->table, $prev_oname).'='.$db->quote($this->prev_name);
					}
					$join_criteria[] = $db->make_ident($this->table, $prev_oid) .'='. $db->make_ident($this->prev_table, 'id');
				}
				else {
					if ($nn_flip) {
						$nn_rels[] = $this->counterpart($name);
					}
					else {
						$nn_rels[] = $name;
					}
				}
			}

		}

		if (count($nn_rels) > 0) {
			$has_nn_rel = TRUE;
		}

		foreach ($nn_rels as $nn_rel) {
			if ($nn_rel == '*') continue;
			$join_criteria[] =  $db->make_ident($nn_table, 'type') . '=' . $db->quote($nn_rel);
		}

		return $join_criteria;

	}

	private function finish_rels() {

		$db = $this->db;

		$rels = $this->rels;

		if (count($rels) == 0) {
			//匿名关系 a b 		a.b_id = b or b.a_id = a
			$rels[] = array($this->name, $this->prev_name, FALSE);		
		}

		$join_tables = array();
		$join_criteria = $this->where;
		$this->where = array();

		$join_tables[] = $db->make_ident($this->real_name($this->prev_name)).' '.$db->quote_ident($this->prev_table);

		// 对象名的顺序
		$name_order = strcmp($this->name, $this->prev_name);
		if ($name_order >= 0) {
			$rela_name = self::RELA_PREFIX.$this->name.'_'.$this->prev_name;
			$rel_id1 = $db->make_ident($this->table, 'id');
			$rel_id2 = $db->make_ident($this->prev_table, 'id');
			$nn_flip = FALSE;
		}
		else {
			$rela_name = self::RELA_PREFIX.$this->prev_name.'_'.$this->name;
			$rel_id1 = $db->make_ident($this->prev_table, 'id');
			$rel_id2 = $db->make_ident($this->table, 'id');
			$nn_flip = TRUE;
		} 	

		$nn_table = 'r'.(self::$_table_guid ++);

		$has_nn_rel = FALSE;
		$has_rel = FALSE;

		$and_rels = array();
		foreach ($rels as $part) {

			list($name, $prev_name, $or_op) = $part;

			if ($or_op) {
				$or_rels[] = array($name, $prev_name);
				continue;
			}
			elseif (count($or_rels)>0) {
				$criteria = $this->parse_rel_criteria($or_rels, $nn_table, $nn_flip, $has_nn_rel);
				if (count ($criteria) > 0) {
					$join_criteria[] = $this->pack_where($criteria, 'OR');
					$has_rel = TRUE;
				}
				$or_rels = array();
			}

			$and_rels[] = array($name, $prev_name);
		}

		if (count($or_rels)>0) {
			$criteria = $this->parse_rel_criteria($or_rels, $nn_table, $nn_flip, $has_nn_rel);
			if (count ($criteria) > 0) {
				$join_criteria[] = $this->pack_where($criteria, 'OR');
				$has_rel = TRUE;
			}
			$or_rels = array();
		}

		$criteria = $this->parse_rel_criteria($and_rels, $nn_table, $nn_flip, $has_nn_rel);
		if (count ($criteria) > 0) {
			$join_criteria[] = $this->pack_where($criteria, 'AND');
			$has_rel = TRUE;
		}

		if (!$has_rel && !$has_nn_rel) {
			$join_criteria[] = $db->make_ident($nn_table, 'type').'=""';
			$has_nn_rel = TRUE;
		}

		if ($has_nn_rel) {

			$join_tables[] = ($nn_flip ? $db->make_ident($rela_name) : $db->make_ident($rela_name))
				.' '.$nn_table;
			// 对象名相同 则同时比较两个方向的关系
			if ($name_order == 0) {
				$_c1 = $this->pack_where(array($db->make_ident($nn_table, 'id1').'='.$rel_id1, $db->make_ident($nn_table, 'id2').'='.$rel_id2), 'AND');
				$_c2 = $this->pack_where(array($db->make_ident($nn_table, 'id1').'='.$rel_id2, $db->make_ident($nn_table, 'id2').'='.$rel_id1), 'AND');
				$join_criteria[] = $this->pack_where(array($_c1, $_c2), 'OR');
			}
			else {
				$join_criteria[] = $db->make_ident($nn_table, 'id1').'='.$rel_id1;
				$join_criteria[] = $db->make_ident($nn_table, 'id2').'='.$rel_id2;
			}

		}

		$this->join_tables = array_merge($this->join_tables, $join_tables);
		$this->join_criteria = array_merge($this->join_criteria, $join_criteria);

	}

	public function parse_rest($text) {

		$skip_filters = FALSE;

		$count = 0;
		//处理#id
		$text = preg_replace_callback('/^\s*#(\d+)/u', array($this, 'parse_id'), $text, -1, $count);
		if ($count > 0) {
			//如果存在id 则其他条件无意义
			$skip_filters = TRUE;
		}

		//处理pseudo
		$text = preg_replace_callback('/:(\w+)\(((?:\\[\)]|[^\)])+)\)/u', array($this, 'parse_pseudo'), $text);

		//处理条件过滤
		if (!$skip_filters) {

			$text = preg_replace_callback('/\[\s*((?:[^\[\]](?:\\[\[\]])?)+)\s*\]/u', array($this, 'parse_filter'), $text);

		}

		//处理与上一级的关系
		if ($this->prev_name) {
			$text = preg_replace_callback('/\.([\w-]+|\*|\@\(((?:\\[\)]|[^\)])+)\))/u', array($this, 'parse_rel'), $text);
			$this->finish_rels();
		}

		//处理与下一级的关系
		$this->rels = array();
		$text = preg_replace_callback('/<([\w-]+|\*|\@\(((?:\\[\)]|[^\)])+)\))/u', array($this, 'parse_prel'), $text);

	}

	private function store_unit($finalize = FALSE) {
		$this->log('STORE_UNIT %d WITH SEP %s', $finalize, $this->sep);
		switch ($this->sep) {
		case '|':	//或运算  (a|b) c = c left join a, c left join b where a IS NOT NULL or b IS NOT NULL
			//TODO: 或运算
			$this->store($this->or_query);
			if ($finalize) {
				$this->prev_or_query = $this->or_query;
				$this->or_query = NULL;
				$this->sep = NULL;
			}
			break;
		default:	//与运算  (a,b) c = c inner join a inner join b
			$this->store($this->and_query);
			if ($finalize) {
				$this->prev_and_query = $this->and_query;
				$this->and_query = NULL;
				$this->sep = NULL;
			}
			break;
		}
	}

	function __construct($db) {
		$this->db = $db;
	}

	//数据对象正则模式
	//object:relationship[expression]
	const PATTERN_UNIT = '`(
		(?:
			\(
				(?:
					\([^()]+\)
					|[^()]+
				)+
			\)
			|\[
				(?:\\\]|[^\]])+	
			\]
			|[^ ,(\[|]+
		)+
	)\s*([|,]?)\s*`ux';

	const PATTERN_NAME = '/^(\w+)(.*)?/u';

	//新建对象正则模式  
	//new object
	const PATTERN_EMPTY = '/^\s*(\w+):empty\s*$/';
	const ESCAPE_CHARS = '[]|,"\'';

	private static $_table_guid = 0;
	static function reset_table_counter() {
		self::$_table_guid = 0;
	}

	function parse_selector($selector) {
		
		$offset = 0;
		while (0 < preg_match(self::PATTERN_UNIT, $selector, $part, PREG_OFFSET_CAPTURE, $offset)) {
			$unit = $part[1][0];
			$offset = $part[0][1] + strlen($part[0][0]);
			$sep = $part[2][0];
			
			//检查是否有子选择
			if ($unit[0] == '(') {
				$this->store();
				$this->parse_selector(preg_replace('/^\((.+)\)$/', '$1', $unit));
				$this->store_unit(TRUE);
				$this->restore(); //从还原点删除原来的$query, 但是不恢复query状态
			}
			//提取name
			else {
				if(!preg_match(self::PATTERN_NAME, $unit, $matches)) break;

				$this->name = $matches[1];
				$rest = $matches[2];

				$this->table = 't'.(self::$_table_guid++);

				if (!$this->alias[$this->name]) $this->alias[$this->name] = $this->table;
				$this->table_name[$this->table] = $this->name;

				if (count($this->prev_and_query) > 0) {
					while ($this->restore(TRUE, $this->prev_and_query)) {
						$this->parse_rest($rest);
						$this->finalize_join();
					}
				}
				elseif (count($this->prev_or_query) > 0) {
					//或运算  (a|b) c = c left join a, c left join b where a IS NOT NULL or b IS NOT NULL
					$or_where = array();
					while($this->restore(TRUE, $this->prev_or_query)) {
						$this->parse_rest($rest);
						$this->finalize_join('left');
						$or_where[] = $this->db->make_ident($this->prev_table, 'id').' IS NOT NULL';
					}
					$this->where[] = $this->pack_where($or_where, 'OR');
				}
				else {
					$this->parse_rest($rest);
				}

			}

			$this->prev_name = $this->name;
			$this->prev_table = $this->table;

			//设置多路输入运算
			if ($sep) {
				if (!$this->sep) {
					$this->sep = $sep;
				}
				$this->store_unit();
				$this->restore(FALSE);			
			}

		}

	}

	function makeSQL() {

		if ($this->name) {

			$db = $this->db;

			$SQL = $db->make_ident($this->real_name($this->name)).' '.$db->quote_ident($this->table);
			$this->finalize_join();

			if ($this->join) {
				$SQL .= ' '. implode(' ', array_reverse($this->join));
			}

			if ($this->where) {
				$SQL .= ' WHERE '. $this->pack_where($this->where, 'AND');
			}

			$SQL = trim($SQL);

			if ($this->union) {
				$SQL = '('.$SQL.') UNION ('.implode(') UNION (', $this->union).')';
				$count_SQL = 'SELECT COUNT(*) count FROM ('.$SQL.') union_table';
				if ($this->order_by) {
					$order_by = array();
					//从ORDER_BY字符串中移除个别表名称, 因为对UNION表的排序不能使用个别表的field
					foreach($this->order_by as $o) {
						if (preg_match('/^'.preg_quote($this->table).'\.(.+)$/', $o, $parts)) {
							$order_by[] = $parts[1];
						}
					}
					$SQL .= ' ORDER BY '.implode(', ', $order_by);
				}
				else {
					$SQL .= ' ORDER BY '.$db->make_ident($this->table, 'id');
				}
			}
			else {
				$count_SQL = $SQL;
				if ($this->order_by) {
					$SQL .= ' ORDER BY '.implode(', ', $this->order_by);
				}
				else {
					$SQL .= ' ORDER BY '.$db->make_ident($this->table, 'id');
				}
			}

			if ($this->limit) {
				$SQL .= ' LIMIT '.$this->limit;
			}

			$this->from_SQL = $SQL;
			$this->SQL = 'SELECT DISTINCT '.$db->make_ident($this->table, 'id').' FROM '.$SQL;
			$this->count_SQL = 'SELECT COUNT(DISTINCT '.$db->make_ident($this->table, 'id').') count FROM ' . $count_SQL;

		}

	}

}

