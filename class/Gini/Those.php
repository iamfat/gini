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
        private $_from;
        private $_where;
        private $_join;
        private $_joinedTables;
        private $_alias;
        private $_order_by;
        private $_limit;

        private $_condition;

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
            $db = $this->db();
            $this->_from = [
                $db->ident($this->tableName()) . ' AS ' . $db->quoteIdent($this->_table)
            ];

            if ($criteria) {
                if (!is_array($criteria)) {
                    $criteria = ['id' => $criteria];
                }
                foreach ($criteria as $key => $value) {
                    $whose = new \Gini\Those\Whose($key);
                    if (is_array($value)) {
                        $this->meet($whose->isIn($value));
                    } else {
                        $this->meet($whose->is($value));
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

        protected function fieldName($field, $suffix = null)
        {
            return \Gini\Those\Whose::fieldName($this, $field . $suffix);
        }

        public static function packWhere($where, $op = 'AND')
        {
            if (!is_array($where)) {
                $where = [$where];
            }
            if (count($where) <= 1) {
                return $where[0];
            }

            return '(' . implode(' ' . $op . ' ', $where) . ')';
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
            $this->finalizeCondition();
            if ($this->_where) {
                $this->_where[] = 'AND';
            }
            $this->_condition = new \Gini\Those\Whose($field);
            return $this;
        }

        public function andWhose($field)
        {
            return $this->whose($field);
        }

        public function orWhose($field)
        {
            $this->resetFetch();
            $this->finalizeCondition();
            if ($this->_where) {
                $this->_where[] = 'OR';
            }
            $this->_condition = new \Gini\Those\Whose($field);
            return $this;
        }

        public function __call($method, $params)
        {
            if ($method === __FUNCTION__) {
                return;
            }

            if ($this->_condition && method_exists($this->_condition, $method)) {
                $ret = call_user_func_array([$this->_condition, $method], $params);
                return $ret === $this->_condition ? $this : $ret;
            }

            throw new \BadMethodCallException();
        }

        public function methodExists($method)
        {
            if (method_exists($this, $method)) {
                return true;
            }

            if ($this->_condition && method_exists($this->_condition, $method)) {
                return true;
            }

            return false;
        }

        public function whoAre($field)
        {
            $this->resetFetch();
            $this->finalizeCondition();
            if ($this->_where) {
                $this->_where[] = 'AND';
            }
            $this->_condition = new \Gini\Those\WhoAre($field);
            return $this;
        }

        public function andWhoAre($field)
        {
            return $this->whoAre($field);
        }

        public function orWhoAre($field)
        {
            $this->resetFetch();
            $this->finalizeCondition();
            if ($this->_where) {
                $this->_where[] = 'OR';
            }
            $this->_condition = new \Gini\Those\WhoAre($field);
            return $this;
        }

        public function whoIsThe($field)
        {
            return $this->whoAre($field);
        }

        public function andWhoIsThe($field)
        {
            return $this->whoAre($field);
        }

        public function orWhoIsThe($field)
        {
            return $this->orWhoAre($field);
        }

        public function whichAre($field)
        {
            return $this->whoAre($field);
        }

        public function andWhichAre($field)
        {
            return $this->whoAre($field);
        }


        public function orWhichAre($field)
        {
            return $this->orWhoAre($field);
        }

        public function whichIsThe($field)
        {
            return $this->whoAre($field);
        }

        public function andWhichIsThe($field)
        {
            return $this->whoAre($field);
        }

        public function orWhichIsThe($field)
        {
            return $this->orWhoAre($field);
        }

        public function alias($name = null)
        {
            $this->resetFetch();
            $this->_alias[$name] = $this->_table;
            return $this;
        }

        public function orderBy($field, $mode = 'asc')
        {
            $this->resetFetch();
            $fieldName = \Gini\Those\Whose::fieldName($this, $field);

            $mode = strtolower($mode);
            switch ($mode) {
                case 'desc':
                case 'd':
                    $this->_order_by[$fieldName] = 'DESC';
                    break;
                case 'asc':
                case 'a':
                    $this->_order_by[$fieldName] = 'ASC';
                    break;
            }

            return $this;
        }

        public function finalizeCondition()
        {
            if ($this->_condition) {
                $condition = $this->_condition;
                $this->_condition = null;
                $this->_meet($condition);
            }
        }

        private $fieldsFilter;
        protected function fields()
        {
            $fields = parent::fields();
            return isset($this->fieldsFilter) ? \array_intersect_key($fields, $this->fieldsFilter) : $fields;
        }

        public function withFields(...$fields)
        {
            if (count($fields) < 1 || $fields[0] === '*') {
                $this->fieldsFilter = null;
            } else {
                $this->fieldsFilter = array_combine($fields, $fields);
            }
            $this->setFetchFlag('data', false);
            return $this;
        }

        public function makeSQL()
        {
            if (!$this->_withTrashed) {
                $properties = a($this->name)->properties();
                if (isset($properties['deleted_at'])) {
                    $this->andWhose('deleted_at')->is(null);
                }
            }

            $this->finalizeCondition();

            $db = $this->db;
            $table = $this->_table;

            if ($this->_join) {
                $this->_from[0] .= ' ' . implode(' ', $this->_join);
            }

            $from_SQL = ' FROM ' . implode(', ', $this->_from);
            $this->from_SQL = $from_SQL;

            $where_SQL = '';
            if ($this->_where) {
                $where_SQL = ' WHERE ' . implode(' ', $this->_where);
            }
            $this->where_SQL = $where_SQL;

            $order_SQL = '';
            if ($this->_order_by) {
                $order_by = array_map(function ($k, $v) {
                    return "$k $v";
                }, array_keys($this->_order_by), array_values($this->_order_by));
                $order_SQL = ' ORDER BY ' . implode(', ', $order_by);
            }

            $limit_SQL = '';
            if ($this->_limit) {
                $limit_SQL = ' LIMIT ' . $this->_limit;
            }

            $fields = $this->fields();
            unset($fields['id']);
            $quoted_fields = array_map(function ($field) use ($db, $table) {
                return $db->ident($table, $field);
            }, array_keys($fields));

            $id_col = $db->ident($table, 'id');
            array_unshift($quoted_fields, $id_col);
            if ($this->_order_by) {
                array_push($quoted_fields, ...array_keys($this->_order_by));
            }

            $this->SQL = trim("SELECT DISTINCT " . implode(',', array_unique($quoted_fields))
                . "$from_SQL$where_SQL$order_SQL$limit_SQL");
            $this->count_SQL = trim("SELECT COUNT(DISTINCT $id_col) AS \"count\" $from_SQL $where_SQL");

            return $this;
        }

        public function context($name = '*', $value = null)
        {
            switch ($name) {
                case 'current-table':
                    return $this->_table;

                case 'from':
                    if ($value !== null) {
                        $this->_from = (array)$value;
                    }
                    return $this->_from;

                case 'alias':
                    if ($value !== null) {
                        $this->_alias = (array)$value;
                    }
                    return $this->_alias;

                case 'join':
                    if ($value !== null) {
                        $this->_join = (array)$value;
                    }
                    return $this->_join;

                case 'where':
                    if ($value !== null) {
                        $this->_where = (array)$value;
                    }
                    return $this->_where;
                case 'joined-tables':
                    if ($value !== null) {
                        $this->_joinedTables = (array)$value;
                    }
                    return $this->_joinedTables;
                case 'tables':
                    $tables = [];
                    foreach ((array)$this->_joinedTables as $k => $v) {
                        $tables[$this->name . '.' . $k] = $v;
                    }
                    $tables[$this->name] = $this->_table;
                    return $tables;
                case '*':
                    return [
                        'current-table' => $this->context('current-table'),
                        'joined-tables' => $this->context('joined-tables'),
                        'join' => $this->context('join'),
                        'where' => $this->context('where'),
                        'alias' => $this->context('alias'),
                    ];
            }
        }

        private function _meet($condition)
        {
            $where = $condition->createWhere($this);
            if ($where === false) {
                // remove prev op (AND or OR)
                if ($this->_where) {
                    array_pop($this->_where);
                }
            } else {
                $this->_where[] = $where;
            }
        }

        public function meet(...$conditions)
        {
            $condition = count($conditions) > 1 ? allOf(...$conditions) : $conditions[0];
            $this->finalizeCondition();
            if ($this->_where) {
                $this->_where[] = 'AND';
            }
            $this->_condition = $condition;
            return $this;
        }
    }
}

namespace {
    class_exists('\Gini\ORM');

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
            return new \Gini\Those($name, $criteria);
        }
    }

    if (function_exists('whose')) {
        die('whose() was declared by other libraries, which may cause problems!');
    } else {
        function whose($name)
        {
            return new \Gini\Those\Whose($name);
        }
    }

    if (function_exists('anyOf')) {
        die('anyOf() was declared by other libraries, which may cause problems!');
    } else {
        function anyOf()
        {
            $params = func_get_args();
            return new \Gini\Those\AnyOf($params);
        }
    }

    if (function_exists('allOf')) {
        die('allOf() was declared by other libraries, which may cause problems!');
    } else {
        function allOf()
        {
            $params = func_get_args();
            return new \Gini\Those\AllOf($params);
        }
    }

    if (function_exists('whoAre')) {
        die('whoAre() was declared by other libraries, which may cause problems!');
    } else {
        function whoAre($name)
        {
            return new \Gini\Those\WhoAre($name);
        }
    }
}
