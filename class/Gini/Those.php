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

$sensors = those('sensors')->whose('subscriber.name')->is('ABC');
$sensors = those('sensors')->whose('subscriber')->is($user);

// sensor/subscriber [sensor] [subscriber]
// subscriber [name]

*/

namespace Gini {

    class Those extends ORMIterator
    {
        private $_table;
        private $_field;
        private $_where;
        private $_join;
        private $_joinedTables;
        private $_alias;
        private $_whoAreField;

        private $_withTrashed = false;

        private static $_uniqid = 0;

        public function uniqid()
        {
            return self::$_uniqid++;
        }

        public static function reset()
        {
            self::$_uniqid = 0;
        }

        public static function setup()
        {
        }

        public function __construct($name, $criteria = null)
        {
            parent::__construct($name);
            $this->_table = 't' . $this->uniqid();
            if ($criteria) {
                if (!is_array($criteria)) {
                    $criteria = ['id' => $criteria];
                }
                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $this->whose($key)->isIn($value);
                    } else {
                        $this->whose($key)->is($value);
                    }
                }
            }
        }

        public function withTrashed()
        {
            $this->_withTrashed = true;

            return $this;
        }

        public function onlyTrashed()
        {
            $this->_withTrashed = true;

            return $this->whose('deleted_at')->is(null);
        }

        protected function fetch($scope = 'fetch')
        {
            if (!$this->SQL) {
                $this->makeSQL();
            }

            return parent::fetch($scope);
        }

        private function _getValue($v)
        {
            if ($v instanceof \Gini\Those\SQL) {
                return strval($v);
            }
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
            if (!is_array($where)) {
                $where = [$where];
            }
            if (count($where) <= 1) {
                return $where[0];
            }

            return '('.implode(' '.$op.' ', $where).')';
        }

        public function limit($start, $per_page = null)
        {
            $this->resetFetch();

            if ($per_page > 0) {
                $this->_limit = sprintf('%d, %d', $start, $per_page);
            } else {
                $this->_limit = sprintf('%d', $start);
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
            if ($this->_where) {
                $this->_where[] = 'OR';
            }
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
            $field_name = $this->_fieldName($this->_field);

            $v = reset($values);
            if ($v instanceof self) {
                $field_name = $this->_fieldName($this->_field, '_id');
                $this->_join[] = 'INNER JOIN '.$db->ident($v->table_name).' AS '.$db->quoteIdent($v->_table)
                    .' ON '.$field_name.'='.$db->ident($v->_table, 'id');
                if ($v->_join) {
                    $this->_join = array_merge($this->_join, $v->_join);
                }

                $op = 'AND';
                if (is_array($this->_where)) {
                    while (in_array(end($this->_where), ['AND', 'OR'])) {
                        $op = array_pop($this->_where);
                    }
                }

                if ($v->_where) {
                    if ($this->_where) {
                        $this->_where = array_merge($this->_where, [$op], $v->_where);
                    } else {
                        $this->_where = $v->_where;
                    }
                }
            } else {
                foreach ($values as $v) {
                    $qv[] = $db->quote($v);
                }
                $this->_where[] = $field_name.' IN ('.implode(', ', $qv).')';
            }

            return $this;
        }

        public function isNotIn()
        {
            $values = func_get_args();

            $db = $this->db;
            $field_name = $this->_fieldName($this->_field);

            $v = reset($values);
            if ($v instanceof self) {
                $field_name = $this->_fieldName($this->_field, '_id');
                $this->_join[] = 'LEFT JOIN '.$db->ident($v->table_name)
                    .' AS '.$db->quoteIdent($v->_table)
                    .' ON '.$field_name.'='.$db->ident($v->_table, 'id');
                if ($v->_join) {
                    $this->_join = array_merge($this->_join, $v->_join);
                }

                if ($v->_where) {
                    $this->_join = array_merge($this->_join, [ 'AND' ], $v->_where);
                }

                $op = 'AND';
                if (is_array($this->_where)) {
                    while (in_array(end($this->_where), ['AND', 'OR'])) {
                        $op = array_pop($this->_where);
                    }
                }

                if ($this->_where) {
                    $this->_where[] = 'AND';
                }
                $this->_where[] = $db->ident($v->_table, 'id').' IS NULL';
            } else {
                foreach ($values as $v) {
                    $qv[] = $db->quote($v);
                }
                $this->_where[] = $field_name.' NOT IN ('.implode(', ', $qv).')';
            }

            return $this;
        }

        public function isRelatedTo($value)
        {
            assert($this->_field);

            $db = $this->db;
            $field_name = $this->_fieldName($this->_field);
            $this->_where[] = 'MATCH(' . $field_name.') AGAINST (' . $db->quote($value).')';

            return $this;
        }

        protected function _fieldName($field,$suffix=null)
        {
            $db = $this->db;
            $name = $this->name();
            // table1.table2.table3.field
            $fields = explode('.', $field);
            $key = '';

            for (;;) {
                // chop field one by one
                $field = array_shift($fields);
                $fieldKey = $key ? $key.'.'.$field : $field;
                $table = $key ? $this->_joinedTables[$key] : $this->_table;

                $o = a($name);
                $structure = $o->structure();
                $manyStructure = $o->manyStructure();
                if (isset($manyStructure[$field])) {
                    // it is a many-field, a pivot table required
                    $pivotName = $o->pivotTableName($field);
                    $pivotKey = "$fieldKey@pivot";
                    if (!isset($this->_join[$pivotKey])) {
                        $this->_joinedTables[$pivotKey] = $pivotTable = 't'.$this->uniqid();
                        $this->_join[$pivotKey] = 'INNER JOIN '.$db->ident($pivotName)
                            .' AS '.$db->quoteIdent($pivotTable)
                            .' ON '.$db->ident($pivotTable, $name.'_id').'='.$db->ident($table, 'id');
                    }
                } else {
                    $pivotName = null;
                }

                if (count($fields) == 0
                    || (isset($structure[$field]) && !isset($structure[$field]['object']))
                    || (isset($manyStructure[$field]) && !isset($manyStructure[$field]['object']))
                    ) {
                    if ($pivotName) {
                        $pivotKey = "$fieldKey@pivot";
                        $pivotTable = $this->_joinedTables[$pivotKey];
                        return $db->ident($pivotTable, $field.$suffix);
                    } else {
                        return $db->ident($table, $field.$suffix);
                    }
                } else {
                    if ($pivotName) {
                        $fieldName = $manyStructure[$field]['object'];
                        $tableName = a($fieldName)->tableName();
                        if (!isset($this->_join[$fieldKey])) {
                            $this->_joinedTables[$fieldKey] = $fieldTable = 't'.$this->uniqid();
                            $pivotKey = "$fieldKey@pivot";
                            $pivotTable = $this->_joinedTables[$pivotKey];
                            $this->_join[$fieldKey] = 'INNER JOIN '.$db->ident($tableName)
                                .' AS '.$db->quoteIdent($fieldTable)
                                .' ON '.$db->ident($pivotTable, $field.'_id').'='.$db->ident($fieldTable, 'id');
                        }
                    } else {
                        $fieldName = $structure[$field]['object'];
                        $tableName = a($fieldName)->tableName();
                        if (!isset($this->_join[$fieldKey])) {
                            $this->_joinedTables[$fieldKey] = $fieldTable = 't'.$this->uniqid();
                            $this->_join[$fieldKey] = 'INNER JOIN '.$db->ident($tableName)
                                .' AS '.$db->quoteIdent($fieldTable)
                                .' ON '.$db->ident($table, $field.'_id').'='.$db->ident($fieldTable, 'id');
                        }
                    }

                    $name = $fieldName;
                    $key = $fieldKey;
                }
            }
        }

        public function match($op, $v)
        {
            assert($this->_field);

            $db = $this->db;
            $field_name = $this->_fieldName($this->_field);

            switch ($op) {
                case '^=': {
                    $this->_where[] = $field_name.' LIKE '.$db->quote($v.'%');
                }
                break;

                case '$=': {
                    $this->_where[] = $field_name.' LIKE '.$db->quote('%'.$v);
                }
                break;

                case '*=': {
                    $this->_where[] = $field_name.' LIKE '.$db->quote('%'.$v.'%');
                }
                break;

                case '=': case '<>': {
                    if (is_object($v)) {
                        $o = a($this->name);
                        $field = $this->_field;
                        $structure = $o->structure();
                        $manyStructure = $o->manyStructure();
                        $obj_where = [];
                        if ((isset($structure[$field])
                                && \array_key_exists('object', $structure[$field])
                                && !$structure[$field]['object'])
                            || (isset($manyStructure[$field])
                                && \array_key_exists('object', $manyStructure[$field])
                                && !$manyStructure[$field]['object'])
                            ) {
                            $obj_where[] = $this->_fieldName($this->_field,'_name').$op.$db->quote($v->name());
                        }
                        $obj_where[] = $this->_fieldName($this->_field,'_id').$op.intval($v->id);
                        if ($op == '<>') {
                            $this->_where[] = $this->_packWhere($obj_where, 'OR');
                        } else {
                            $this->_where[] = $this->_packWhere($obj_where, 'AND');
                        }
                        break;
                    } elseif (is_null($v)) {
                        if ($op == '<>') {
                            $this->_where[] = $field_name.' IS NOT NULL';
                        } else {
                            $this->_where[] = $field_name.' IS NULL';
                        }
                        break;
                    }
                }

                default: {
                    $this->_where[] = $field_name.$op.$this->_getValue($v);
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
            $field_name = $this->_fieldName($this->_field);
            $this->_where[] = '('.$field_name.'>='.$this->_getValue($a).
                ' AND '.$field_name.'<'.$this->_getValue($b).')';

            return $this;
        }

        public function orderBy($field, $mode = 'asc')
        {
            $this->resetFetch();
            $originalField = $this->_field;
            $this->_field = $field;
            $field_name = $this->_fieldName($this->_field);
            $this->_field = $originalField;

            $mode = strtolower($mode);
            switch ($mode) {
                case 'desc':
                case 'd':
                $this->_order_by[] = $field_name .' DESC';
                break;
                case 'asc':
                case 'a':
                $this->_order_by[] = $field_name .' ASC';
                break;
            }

            return $this;
        }

        public function makeSQL()
        {
            $db = $this->db;
            $table = $this->_table;

            if (!$this->_withTrashed) {
                $properties = a($this->name)->properties();
                if (isset($properties['deleted_at'])) {
                    $this->andWhose('deleted_at')->is(null);
                }
            }

            $from_SQL = 'FROM '.$db->ident($this->table_name).' AS '.$db->quoteIdent($this->_table);

            if ($this->_join) {
                $from_SQL .= ' '.implode(' ', $this->_join);
            }

            if ($this->_where) {
                $from_SQL .= ' WHERE '.implode(' ', $this->_where);
            }

            $this->from_SQL = $from_SQL;

            if ($this->_order_by) {
                $order_SQL = 'ORDER BY '.implode(', ', $this->_order_by);
            }

            if ($this->_limit) {
                $limit_SQL = 'LIMIT '.$this->_limit;
            }

            $fields = $this->fields();
            unset($fields['id']);
            $quoted_fields = array_map(function ($field) use ($db, $table) {
                return $db->ident($table, $field);
            }, array_keys($fields));
            $id_col = $db->ident($table, 'id');

            $this->SQL = trim("SELECT DISTINCT $id_col" . ($quoted_fields ? ',' . implode(',', $quoted_fields) : '')
                . " $from_SQL $order_SQL $limit_SQL");
            $this->count_SQL = trim("SELECT COUNT(DISTINCT $id_col) AS \"count\" $from_SQL");

            return $this;
        }

        public function whoAre($field)
        {
            $this->resetFetch();
            if ($this->_where) {
                $this->_where[] = 'AND';
            }
            $this->_whoAreField = $field;

            return $this;
        }

        public function of($those)
        {
            assert($this->_whoAreField);
            $this->_joinWhoAreTables($this->_whoAreField);
            // 完成反转后把those的条件复制过来
            $thoseInfo = $those->context();
            $mirrorTables = [];
            $needJoin = [];
            foreach ($thoseInfo['tables'] as $key => $v) {
                if (isset($this->_joinedTables[$key])) {
                    $mirrorTables[$v] = $this->_joinedTables[$key];
                } else {
                    $this->_joinedTables[$key] = $mirrorTables[$v] = 't' . $this->uniqid();
                    if ($thoseInfo['join'][$key]) {
                        $needJoin[$key] = $thoseInfo['join'][$key];
                    }
                }
            }

            foreach ($needJoin as $k => $v) {
                $this->_join[$k] = $this->mirrorSQL($v, $mirrorTables);
            }

            foreach ($thoseInfo['where'] as $where) {
                $this->_where[] = $this->mirrorSQL($where, $mirrorTables);
            }
            return $this;

        }

        private function mirrorSQL($sql, $mirror)
        {
            foreach ($mirror as $k => $v) {
                $sql = str_replace($k, $v, $sql);
            }
            return $sql;
        }

        // whoAre的参数是一个其他orm产生的反查，所以不能用普通的方式生成join
        private function _joinWhoAreTables($whoAreField)
        {
            $db = $this->db;
            $basefields = explode('.', $whoAreField);
            $fields = $basefields;
            $basefield = array_shift($fields);
            $obj = a($basefield);
            $objects[$basefield]['object'] = $obj;
            while (!empty($fields)) {
                $field = array_shift($fields);
                $o = $objects[$basefield]['object'];
                $structure = $o->structure();
                $manyStructure = $o->manyStructure();
                if (isset($manyStructure[$field])) {
                    $objects[$basefield]['pivot'] = $field;
                    $basefield = $basefield . '.' . $field;
                    $objects[$basefield]['object'] = a($manyStructure[$field]['object']);
                } else {
                    $basefield = $basefield . '.' . $field;
                    $objects[$basefield]['object'] = a($structure[$field]['object']);
                }
            }

            $basetable = $this->_table;
            $fields = $basefields;
            while (count($fields) > 1) {
                $field = array_pop($fields);
                $table = join('.', $fields);
                $o = $objects[$table];
                if (isset($o['pivot'])) {
                    $pivotKey = $table . '@pivot';
                    $pivotName = $o['object']->pivotTableName($field);
                    if (!isset($this->_join[$pivotKey])) {
                        $this->_joinedTables[$pivotKey] = $pivotTable = 't' . $this->uniqid();
                        $this->_join[$pivotKey] = 'INNER JOIN ' . $db->ident($pivotName)
                            . ' AS ' . $db->quoteIdent($pivotTable)
                            . ' ON ' . $db->ident($pivotTable, $field . '_id') . '=' . $db->ident($basetable, 'id');
                        $this->_joinedTables[$table] = $joinTable = 't' . $this->uniqid();
                        $tablename = $o['object']->tableName();
                        $this->_join[$table] = 'INNER JOIN ' . $db->ident($tablename)
                            . ' AS ' . $db->quoteIdent($joinTable)
                            . ' ON ' . $db->ident($joinTable, $field . '_id') . '=' . $db->ident($pivotTable, $tablename . '_id');
                    }
                } elseif (!isset($this->_join[$table])) {
                    $this->_joinedTables[$table] = $joinTable = 't' . $this->uniqid();
                    $tablename = $o['object']->tableName();
                    $this->_join[$table] = 'INNER JOIN ' . $db->ident($tablename)
                        . ' AS ' . $db->quoteIdent($joinTable)
                        . ' ON ' . $db->ident($joinTable, $field . '_id') . '=' . $db->ident($basetable, 'id');
                }
                $basetable = $this->_joinedTables[$table];
            }
        }

        public function context()
        {
            $base = $this->name;
            $res = [];
            foreach ($this->_joinedTables ?: [] as $k => $v) {
                $res['tables'][$base . '.' . $k] = $v;
            }
            $res['tables'][$base] = $this->_table;
            $res['where'] = $this->_where;
            foreach ($this->_join ?: [] as $k => $v) {
                $res['join'][$base . '.' . $k] = $v;
            }
            return $res;
        }


        public function get($key = 'id', $val = null)
        {
            if (!$this->SQL) {
                $this->makeSQL();
            }
            return parent::get($key, $val);
        }

        public function meet($where) {
            if (! $where instanceof \Gini\ORM\Where) {
                throw new \Exception('meet need orm/where object');
            }
            $this->_where[] = 'AND';
            $this->_where[] = $this->createWhereSQL($where);
            return $this;
        }

        public function createWhereSQL($where) {
            if ($where instanceof \Gini\ORM\Condition) {
                return $this->createConditionSQL($where);
            }
            if ($where instanceof \Gini\ORM\Conditions) {
                switch ($where->type) {
                    case (\Gini\ORM\Conditions::TYPE_ANY):
                        $concat = 'or';
                        break;
                    case (\Gini\ORM\Conditions::TYPE_ALL):
                        $concat = 'and';
                        break;
                    default:
                        throw new \Exception('unknown conditions type');
                }
                $wheres = [];
                foreach ($where->object_list as $object) {
                    $wheres[] = $this->createWhereSQL($object);
                }
                return '('.join(' '.$concat.' ', $wheres).')';
            }
            throw new \Exception('unknown orm/where object type');
        }

        public function createConditionSQL($condition) {
            if (! $condition instanceof \Gini\ORM\Condition) {
                throw new \Exception('need condition object');
            }
            $field = $this->_fieldName($condition->column);
            $db = $this->db;

            switch ($condition->operator) {
                case 'like':
                    $where = $field.' LIKE '.$db->quote($condition->params);
                        break;
                case '=':
                case '<>':
                    if (is_object($condition->params)) {
                        $o = a($this->name);
                        $structure = $o->structure();
                        $manyStructure = $o->manyStructure();
                        $obj_where = [];
                        if ((isset($structure[$field])
                                && \array_key_exists('object', $structure[$field])
                                && !$structure[$field]['object'])
                            || (isset($manyStructure[$field])
                                && \array_key_exists('object', $manyStructure[$field])
                                && !$manyStructure[$field]['object'])
                        ) {
                            $obj_where[] = $this->_fieldName($condition->column,'_name').$condition->operator.$db->quote($condition->params->name());
                        }
                        $obj_where[] = $this->_fieldName($condition->column,'_id').$condition->operator.intval($condition->params->id);
                        if ($condition->operator == '<>') {
                            $where = $this->_packWhere($obj_where, 'OR');
                        } else {
                            $where = $this->_packWhere($obj_where, 'AND');
                        }
                    } elseif (is_null($condition->params)) {
                        if ($condition->operator == '<>') {
                            $where =  $field  .' IS NOT NULL';
                        } else {
                            $where = $field.' IS NULL';
                        }
                    } else {
                        $where = $field.$condition->operator.$this->_getValue($condition->params);
                    }
                    break;
                case 'between':
                    $where = '('.$field.'>='.$this->_getValue($condition->params[0]).
                        ' AND '.$field.'<'.$this->_getValue($condition->params[1]).')';
                    break;
                case 'in':
                case 'not in':
                    $v = reset($condition->params);
                    if ($v instanceof self) {
                        $field_name = $this->_fieldName($condition->column, '_id');
                        $this->_join[] = 'INNER JOIN '.$db->ident($v->table_name).' AS '.$db->quoteIdent($v->_table)
                            .' ON '.$field_name.'='.$db->ident($v->_table, 'id');
                        if ($v->_join) {
                            $this->_join = array_merge($this->_join, $v->_join);
                        }

                        if ($v->_where) {
                            $this->_join = array_merge($this->_join, [ 'AND' ], $v->_where);
                        }

                        $where = $db->ident($v->_table, 'id').' IS'.($condition->operator=='in'?' NOT':'').' NULL';
                    } else {
                        foreach ($condition->params as $v) {
                            $qv[] = $db->quote($v);
                        }
                        if (empty($qv)) {
                            if ($condition->operator=='in') {
                                $where = '1 != 1';
                            } else {
                                $where = '1 = 1';
                            }

                        } else {
                            $where = $field.($condition->operator=='in'?'':' NOT').' IN ('.implode(', ', $qv).')';
                        }
                    }
                    break;
                default:
                    $where = $field.$condition->operator.$this->_getValue($condition->params);
            }

            return $where;
        }

        public function getSQL() {
            if (!$this->SQL) {
                $this->makeSQL();
            }
            return $this->SQL;
        }
    }

}

namespace {

    if (function_exists('a')) {
        die('a() was declared by other libraries, which may cause problems!');
    } else {
        /**
         * @param string  $name
         * @param null    $criteria
         *
         * @return \Gini\ORM\Base
         */
        function a($name, $criteria = null)
        {
            $class_name = '\Gini\ORM\\'.str_replace('/', '\\', $name);

            return \Gini\IoC::construct($class_name, $criteria);
        }
    }

    // alias to a()
    if (function_exists('an')) {
        die('an() was declared by other libraries, which may cause problems!');
    } else {
        function an($name, $criteria = null)
        {
            return a($name, $criteria);
        }
    }

    if (function_exists('those')) {
        die('those() was declared by other libraries, which may cause problems!');
    } else {
        /**
         * @param $name
         *
         * @return \Gini\Those
         */
        function those($name, $criteria = null)
        {
            return \Gini\IoC::construct('\Gini\Those', $name, $criteria);
        }
    }

    if (function_exists('SQL')) {
        die('SQL() was declared by other libraries, which may cause problems!');
    } else {
        /**
         * @param $SQL
         *
         * @return \Gini\Those\SQL
         */
        function SQL($SQL)
        {
            return \Gini\IoC::construct('\Gini\Those\SQL', $SQL);
        }
    }

    if (function_exists('whose')) {
        die('whose() was declared by other libraries, which may cause problems!');
    } else {
        function whose($name)
        {
            return new \Gini\ORM\Condition($name);
        }
    }
    if (function_exists('anyOf')) {
        die('anyOf() was declared by other libraries, which may cause problems!');
    } else {
        function anyOf()
        {
            $params = func_get_args();
            return new \Gini\ORM\Conditions('any', $params);
        }
    }
    if (function_exists('allOf')) {
        die('allOf() was declared by other libraries, which may cause problems!');
    } else {
        function allOf()
        {
            $params = func_get_args();
            return new \Gini\ORM\Conditions('all', $params);
        }
    }
}
