<?php

/*

$user = those('users')
    ->whose('id')->isIn(1, 2, 3)
    ->orWhose('atime')->isGreaterThan(3)
    ->andWhose('age')->isBetween(5, 15);

$user = the('user')
    ->whoIsThe('employer')->of(
        those('users')
            ->whose('name')->beginsWith('Zhang')
            ->andWhose('room')->isIn(
                those('room')->whose('building')->is(
                    a('building', 1)
                )
            )
    );

$user = the user who is the employer of
those users whose name begins with Zhang and whose room is in
those room whose building is Building(1).

$users = those('users')
    ->whoAre('employers')->of(
        those('users')
            ->whose('name')->beginsWith('Zhang')
            ->andWhose('room')->isIn(
                those('room')->whose('building')->is(
                    a('building', 1)
                )
            )
    );

$users = those users who are those employers of
those users whose name begins with Zhang and whose room is in
those room whose building is Building(1).

$user = those('users')
    ->alias('father')
    ->whose('friend')->isIn(
        those('users')->whose('parent_name')->is('@father.name')
    );

*/

namespace Gini {

    class Those extends ORMIterator
    {
        private $_table;
        private $_field;
        private $_where;
        private $_join;
        private $_alias;

        private static $_uniqid = 0;
        public function uniqid()
        {
            return (self::$_uniqid++);
        }

        public static function reset()
        {
            self::$_uniqid = 0;
        }

        public static function setup()
        {
        }

        public function __construct($name)
        {
            parent::__construct($name);
            $this->_table = 't'.$this->uniqid();
        }

        protected function fetch($scope='fetch')
        {
            if (!$this->SQL) {
                $this->makeSQL();
            }

            return parent::fetch($scope);
        }

        private function _getValue($v)
        {
            $db = $this->db;
            if (preg_match('/^@(?:(\w+)\.)?(\w+)$/', $v, $parts)) {
                //有可能是某个table的field名
                list(, $table, $field) = $parts;
                if ($table) {
                    while (isset($this->_alias[$table])) {
                        $table = $this->_alias[$table];
                    }
                } else {
                    $table = $this->_table;
                }

                return $db->ident($table, $field);
            }

            return $db->quote($v);
        }

        private function _packWhere($where, $op = 'AND')
        {
            if (!is_array($where)) $where = array($where);
            if (count($where) <= 1) return $where[0];
            return '('.implode( ' '.$op.' ', $where).')';
        }

        public function limit($start, $per_page = null)
        {
            $this->resetFetch();

            if ($per_page > 0) {
                $this->_limit = sprintf("%d, %d", $start, $per_page);
            } else {
                $this->_limit = sprintf("%d", $start);
            }

            return $this;
        }

        public function whose($field)
        {
            $this->resetFetch();
            if ($this->_where) {
                $this->_where[] = 'AND';
            }
            $this->_field = $field;

            return $this;
        }

        public function andWhose($field)
        {
            return $this->whose($field);
        }

        public function orWhose($field)
        {
            $this->resetFetch();
            $this->_where[] = 'OR';
            $this->_field = $field;

            return $this;
        }

        public function whoIs($field)
        {
            $this->resetFetch();
            $this->_field = $field;

            return $this;
        }

        public function andWhoIs($field)
        {
            return $this->whoIs($field);
        }

        public function whichIs($field)
        {
            return $this->whoIs($field);
        }

        public function andWhichIs($field)
        {
            return $this->whoIs($field);
        }

        public function orWhoIs($field)
        {
            $this->resetFetch();
            $this->_where[] = 'OR';
            $this->_field = $field;

            return $this;
        }

        public function orWhichIs($field)
        {
            return $this->orWhoIs($field);
        }

        public function of($those)
        {
            // TO BE IMPLEMENTED
            return $this;
        }

        public function alias($name)
        {
            $this->resetFetch();
            $this->_alias[$name] = $this->_table;

            return $this;
        }

        public function isIn()
        {
            $values = func_get_args();

            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);

            $v = reset($values);
            if ($v instanceof Those) {
                $this->_join[] = 'INNER JOIN '.$db->ident($this->table_name).' AS '.$db->quoteIdent($this->_table)
                    . ' ON ' . $field_name . '=' . $db->ident($v->_table, 'id');
                if ($v->_join) {
                    $this->_join = array_merge($this->_join, $v->_join);
                }
                if ($v->_where) {
                    $this->_where = array_merge($this->_where, $v->_where);
                }
            } else {
                foreach ($values as $v) {
                    $qv[] = $db->quote($v);
                }
                $this->_where[] = $field_name . ' IN (' . implode(', ', $qv) .')';
            }

            return $this;
        }

        public function isNotIn()
        {
            $values = func_get_args();

            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);

            $v = reset($values);
            if ($v instanceof Those) {
                $this->_join[] = 'LEFT JOIN '.$db->ident($this->table_name).' AS '.$db->quoteIdent($this->_table)
                    . ' ON ' . $field_name . '=' . $db->ident($v->_table, 'id');
                if ($v->_join) {
                    $this->_join = array_merge($this->_join, $v->_join);
                }
                if ($v->_where) {
                    $this->_where = array_merge($this->_where, $v->_where);
                    $this->_where[] = 'AND';
                }
                $this->_where[] = $field_name . ' IS NOT NULL';
            } else {
                foreach ($values as $v) {
                    $qv[] = $db->quote($v);
                }
                $this->_where[] = $field_name . ' NOT IN (' . implode(', ', $qv) .')';
            }

            return $this;
        }

        public function match($op, $v)
        {
            assert($this->_field);

            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);

            switch ($op) {
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

                case '=': case '<>': {
                    if (is_object($v)) {
                        $o = a($this->name);
                        $field = $this->_field;
                        $structure = $o->structure();
                        if (array_key_exists('object', $structure[$field])) {
                            if (!$structure[$field]['object']) {
                                $obj_where[] = $db->ident($this->_table, $field . '_name') . $op . $db->quote($v->tableName());

                            }

                            $obj_where[] = $db->ident($this->_table, $field . '_id') . $op . intval($v->id);

                            if ($op == '<>') {
                                $this->_where[] = $this->_packWhere($obj_where, 'OR');
                            } else {
                                $this->_where[] = $this->_packWhere($obj_where, 'AND');
                            }
                            break;
                        }
                    }
                }

                default: {
                    $this->_where[] = $field_name . $op . $this->_getValue($v);
                }

            }

            return $this;
        }

        // is(1), is('hello'), is('@name')
        public function is($v)
        {
            return $this->match('=', $v);
        }

        public function isNot($v)
        {
            return $this->match('<>', $v);
        }

        public function beginsWith($v)
        {
            return $this->match('^=', $v);
        }

        public function contains($v)
        {
            return $this->match('*=', $v);
        }

        public function endsWith($v)
        {
            return $this->match('$=', $v);
        }

        public function isLessThan($v)
        {
            return $this->match('<', $v);
        }

        public function isGreaterThan($v)
        {
            return $this->match('>', $v);
        }

        public function isGreaterThanOrEqual($v)
        {
            return $this->match('>=', $v);
        }

        public function isLessThanOrEqual($v)
        {
            return $this->match('<=', $v);
        }

        public function isBetween($a, $b)
        {
            assert($this->_field);
            $db = $this->db;
            $field_name = $db->ident($this->_table, $this->_field);
            $this->_where[] = '(' . $field_name . '>=' . $this->_getValue($a) .
                ' AND ' . $field_name . '<' . $this->_getValue($b) . ')';

            return $this;
        }

        public function orderBy($field, $mode='asc')
        {
            $this->resetFetch();

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

        public function makeSQL()
        {
            $db = $this->db;
            $table = $this->_table;

            $from_SQL = 'FROM ' . $db->ident($this->table_name).' AS '.$db->quoteIdent($this->_table);

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
            $this->SQL = trim("SELECT DISTINCT $id_col $from_SQL $order_SQL $limit_SQL");
            $this->count_SQL = trim("SELECT COUNT(DISTINCT $id_col) AS \"count\" $from_SQL");

            return $this;
        }

    }

}

namespace {

    if (function_exists('a')) {
        die("a() was declared by other libraries, which may cause problems!");
    } else {
        function a($name, $criteria = null)
        {
            $class_name = '\Gini\ORM\\'.str_replace('/', '\\', $name);

            return \Gini\IoC::construct($class_name, $criteria);
        }
    }

    // alias to a()
    if (function_exists('an')) {
        die("an() was declared by other libraries, which may cause problems!");
    } else {
        function an($name, $criteria = null)
        {
            return a($name, $criteria);
        }
    }

    if (function_exists('those')) {
        die("those() was declared by other libraries, which may cause problems!");
    } else {
        function those($name)
        {
            return \Gini\IoC::construct('\Gini\Those', $name);
        }
    }
}
