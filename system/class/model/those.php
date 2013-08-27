<?php

/*

$user = those('user')
            ->whose('id')->is_in(1, 2, 3)
            ->or_whose('atime')->is_greater_than(3)
            ->and_whose('age')->is_between(5, 15);

$user = those('user')
            ->who_is('employer')->of(
                those('user')
                    ->whose('name')->begins_with('Zhang')
                    ->and_whose('room')->is_in(
                        those('room')->whose('building')->is(1)
                    )
            );

$user = those users who is the employer of 
            those users whose name begins with Zhang and whose room is in 
                those room whose building is Building(1).

$user = those('users')
            ->alias('father')
            ->whose('friend')->is_in(
                those('users')->whose('parent_name')->is('@father.name')
            );

*/

namespace Model {

    class Those extends ORM_Iterator {

        private $_table;
        private $_field;
        private $_where;
        private $_join;
        private $_alias;

        private static $_uniqid = 0;
        function uniqid() {
            return (self::$_uniqid++);
        }

        static function setup() {

        }

        function __construct($name) {
            parent::__construct($name);
           $this->_table = 't'.$this->uniqid();
        }

        protected function fetch($scope='fetch') {
            if (!$this->SQL) {
                $this->make_SQL();
            }
            return parent::fetch($scope);            
        }

        private function get_value($v) {
            $db = $this->db;
            if (preg_match('/^@(?:(\w+)\.)?(\w+)$/', $v, $parts)) {
                //有可能是某个table的field名
                list(, $table, $field) = $parts;
                if ($table) {
                    while (isset($this->_alias[$table])) {
                        $table = $this->_alias[$table];
                    }
                }
                else {
                    $table = $this->_table;
                }
                return $db->ident($table, $field);
            }
            return $db->quote($v);
        }
        
        private function pack_where($where, $op = 'AND') {
            if (!is_array($where)) $where = array($where);
            if (count($where) <= 1) return $where[0];
            return '('.implode( ' '.$op.' ', $where).')'; 
        }

        function limit($start, $per_page = null) {
            
            $this->reset_fetch();

            if ($per_page > 0) {
                $this->_limit = sprintf("%d, %d", $start, $per_page);
            }
            else {
                $this->_limit = sprintf("%d", $start);
            }

            return $this;
        }

        function whose($field) {
            $this->reset_fetch();
            if ($this->_where) {
                $this->_where[] = 'AND';
            }
            $this->_field = $field;
            return $this;
        }

        function and_whose($field) {
            return $this->whose($field);
        }

        function or_whose($field) {
            $this->reset_fetch();
            $this->_where[] = 'OR';
            $this->_field = $field;
            return $this;
        }

        function who_is($field) {
            $this->reset_fetch();
            $this->_field = $field;
             return $this;
        }

        function which_is($field) {
            return $this->who_is($field);
        }

        function and_who_is($field) {
             return $this->who_is($field);
        }

        function and_which_is($field) {
            return $this->who_is($field);
        }

        function or_who_is($field) {
            $this->reset_fetch();
            $this->_where[] = 'OR';
            $this->_field = $field;
            return $this;
        }

        function or_which_is($field) {
            return $this->and_who_is($field);
        }

        function of($those) {
            $this->node->of($those->node);
            return $this;
        }

        function alias($name) {
            $this->reset_fetch();
            $this->_alias[$name] = $this->_table;
            return $this;
        }
            
        function is_in() {
            $values = func_get_args();

            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);

            $v = reset($values);
            if ($v instanceof Those) {
                $this->_join[] = 'INNER JOIN '.$db->ident($this->name).' AS '.$db->quote_ident($this->_table) 
                        . ' ON ' . $field_name . '=' . $db->ident($v->_table, 'id');
                if ($v->_join) {
                    $this->_join = array_merge($this->_join, $v->_join);
                }
                if ($v->_where) {
                    $this->_where = array_merge($this->_where, $v->_where);
                }
            }
            else {
                foreach ($values as $v) {
                    $qv[] = $db->quote($v);
                }
                $this->_where[] = $field_name . ' IN (' . implode(', ', $qv) .')';
            }

            return $this;
        }

        function is_not_in() {
            $values = func_get_args();
            
            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);
            
            $v = reset($values);
            if ($v instanceof Those) {
                $this->_join[] = 'LEFT JOIN '.$db->ident($this->name).' AS '.$db->quote_ident($this->_table)
                        . ' ON ' . $field_name . '=' . $db->ident($v->_table, 'id');
                if ($v->_join) {
                    $this->_join = array_merge($this->_join, $v->_join);
                }
                if ($v->_where) {
                    $this->_where = array_merge($this->_where, $v->_where);
                    $this->_where[] = 'AND';
                }
                $this->_where[] = $field_name . ' IS NOT NULL';
            }
            else {
                foreach ($values as $v) {
                    $qv[] = $db->quote($v);
                }
                $this->_where[] = $field_name . ' NOT IN (' . implode(', ', $qv) .')';
            }
            
            return $this;
        }

        function match($op, $v) {
            assert($this->_field);

            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);

            switch($op) {
                case '^=': {
                    $this->_where[] = $field_name .' LIKE '.$db->quote($v.'%');
                }
                break;

                case '$=': {
                    $this->_where[] = $field_name .' LIKE '.$db->quote('%'.$v);
                }
                break;

                case '*=': {
                    $this->_where[] = $field_name .' LIKE '.$db->quote('%'.$v.'%');
                }
                break;

                case '=': case '!=': {                    
                    if ($v instanceof \ORM\Object) {
                        $class_name = '\\ORM\\'.ucwords($this->name);
                        $o = new $class_name;
                        $field = $this->_field;
                        $structure = $o->structure();
                        if (array_key_exists('object', $structure[$field])) {
                            if (!$structure[$field]['object']) {
                                $obj_where[] = $db->ident($this->_table, $field . '_name') . $op . $db->quote($v->name());

                            }

                            $obj_where[] = $db->ident($this->_table, $field . '_id') . $op . intval($v->id);

                            if ($op == '!=') {
                                $this->_where[] = $this->pack_where($obj_where, 'OR');
                            }
                            else {
                                $this->_where[] = $this->pack_where($obj_where, 'AND');
                            }
                            break;
                        }
                    }
                }
                
                default: {
                    $this->_where[] = $field_name . $op . $this->get_value($v);
                }

            }

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
            assert($this->_field);
            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);
            $this->_where[] = '(' . $field_name . '>=' . $this->get_value($a) . 
                             ' AND ' . $field_name . '<' . $this->get_value($b) . ')';
            return $this;
        }

        function order_by($field, $mode='asc') {
            $this->reset_fetch();

            $db = $this->db;            
            $mode = strtolower($mode);
            switch ($mode) {
            case 'desc':
            case 'd':
                $this->_order_by[] = $db->ident($this->_table, $field) . ' DESC';
                break;
            case 'asc':
            case 'a':
                $this->_order_by[] = $db->ident($this->_table, $field) . ' ASC';
                break;
            }

            return $this;
        }

        function make_SQL() {

            $db = $this->db;
            $table = $this->_table;

            $from_SQL = 'FROM ' . $db->ident($this->name).' AS '.$db->quote_ident($this->_table);

            if ($this->_where) {
                $from_SQL .= ' WHERE ' . implode(' ', $this->_where);
            }

            $this->from_SQL = $from_SQL;

            if ($this->_order_by) {
                $order_SQL = 'ORDER BY ' . implode(', ', $this->_order_by);
            }

            if ($this->_limit) {
                $limit_SQL = 'LIMIT ' . $this->_limit;
            }

            $id_col = $db->ident($table, 'id');
            $this->SQL = "SELECT DISTINCT $id_col $from_SQL $order_SQL $limit_SQL";
            $this->count_SQL = "SELECT COUNT(DISTINCT $id_col) AS \"count\" $from_SQL";

            return $this;
        }

    }

}

namespace {

    function those($name) {
        return new \Model\Those($name);
    }

}