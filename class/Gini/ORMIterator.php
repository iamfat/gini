<?php

namespace Gini;

class ORMIterator implements \Iterator, \ArrayAccess, \Countable
{
    protected $db;

    protected $name;
    protected $table_name;

    protected $current_id;
    protected $objects = [];

    protected $total_count;    //符合selector的数据总数
    protected $count;    //实际获得数据数

    protected $SQL;
    protected $SQL_idents;
    protected $SQL_params;
    protected $count_SQL;

    private $_forUpdate = false;

    public function totalCount()
    {
        $this->fetch('count');

        return (int) $this->total_count;
    }

    public function name()
    {
        return $this->name;
    }

    public function __construct($name)
    {
        // 查询一下看看是不是复数
        $name = \Gini\Config::get('orm.plurals')[$name] ?: $name;
        $this->name = $name;
        $this->table_name = str_replace('/', '_', $name);
        $this->db = a($name)->db();
    }

    public function __clone()
    {
    }

    public function query()
    {
        $db = $this->db;

        $args = func_get_args();

        $this->SQL = $SQL = $args[0];
        $this->SQL_idents = $args[1] ?: null;
        $this->SQL_params = $args[2] ?: null;

        return $this;
    }

    private $_fetch_flag;
    protected function setFetchFlag($scope, $enable = true)
    {
        if ($enable) {
            $this->_fetch_flag[$scope] = true;
        } else {
            if ($scope === '*') {
                unset($this->_fetch_flag);
            } else {
                unset($this->_fetch_flag[$scope]);
            }
        }
    }

    protected function isFetchFlagged($scope)
    {
        return isset($this->_fetch_flag['*']) || isset($this->_fetch_flag[$scope]);
    }

    protected function resetFetch()
    {
        if ($this->SQL) {
            $this->setFetchFlag('*', false);
            $this->SQL = null;
            $this->count_SQL = null;
        }
    }

    private static $_FIELDS = [];
    protected function fields()
    {
        if (!isset(self::$_FIELDS[$this->name])) {
            $schema = a($this->name)->ormSchema();
            $fields = array_keys($schema['fields']);
            self::$_FIELDS[$this->name] = array_combine($fields, $fields);
        }

        return self::$_FIELDS[$this->name];
    }

    protected function fetch($scope = 'data')
    {
        if ($this->isFetchFlagged($scope)) {
            return $this;
        }

        switch ($scope) {
            case 'count':
                if (!isset($this->count_SQL) && $this->SQL) {
                    // guess count_SQL via SQL
                    $count_SQL = preg_replace('/\bSQL_CALC_FOUND_ROWS\b/', '', $this->SQL);
                    $count_SQL = preg_replace('/\sORDER BY.+$/', '', $count_SQL);
                    $count_SQL = preg_replace('/\sLIMIT.+$/', '', $count_SQL);
                    $count_SQL = preg_replace('/^(SELECT)\s(.+?)\s(FROM\s)\s*/', '$1 COUNT($2) AS "count" $3', $count_SQL);
                    $count_SQL = preg_replace('/\bCOUNT\((.+?)\.\*\)\s/', 'COUNT($1."id")', $count_SQL);

                    $this->count_SQL = $count_SQL;
                }
                $this->total_count = $this->count_SQL ? $this->db->value($this->count_SQL, $this->SQL_idents, $this->SQL_params) : 0;
                break;
            default:
                if ($this->SQL) {
                    $SQL = $this->SQL;
                    if ($this->_forUpdate) {
                        $SQL .= ' FOR UPDATE';
                    }
                    $result = $this->db->query($SQL, $this->SQL_idents, $this->SQL_params);

                    $objects = [];

                    if ($result) {
                        $fields = $this->fields();
                        $loaded = false; // flag to show if all fields were loaded
                        $loaded_checked = false; // flag to show if we've already checked once
                        while ($row = $result->row(\PDO::FETCH_ASSOC)) {
                            if (!$loaded_checked) {
                                $loaded = empty(array_diff_key($fields, $row));
                                $loaded_checked = true;
                            }
                            if ($loaded) {
                                // if all fields were loaded, just setData to improve performance
                                $o = a($this->name);
                                $o->setData((array) $row);
                                $objects[$row['id']] = $o;
                            } else {
                                $objects[$row['id']] = true;
                            }
                        }
                    }

                    $this->objects = $objects;
                    $this->count = count($objects);
                    $this->current_id = key($objects);
                }
        }

        $this->setFetchFlag($scope, true);

        return $this;
    }

    public function deleteAll()
    {
        $this->fetch();
        foreach ($this->objects as $id => $object) {
            if (!$this->object($id)->delete()) {
                return false;
            }
        }

        return true;
    }

    public function object($id)
    {
        if ($this->objects[$id] === true) {
            $this->objects[$id] = a($this->name, $id);
        }

        return $this->objects[$id];
    }

    // Iterator Start
    public function rewind()
    {
        $this->fetch();
        reset($this->objects);
        $this->current_id = key($this->objects);
    }

    public function current()
    {
        $this->fetch();

        return $this->object($this->current_id);
    }

    public function key()
    {
        $this->fetch();

        return $this->current_id;
    }

    public function next()
    {
        $this->fetch();
        next($this->objects);
        $this->current_id = key($this->objects);

        return $this->object($this->current_id);
    }

    public function valid()
    {
        $this->fetch();

        return isset($this->objects[$this->current_id]);
    }
    // Iterator End

    // Countable Start
    public function count()
    {
        $this->fetch();

        return (int) $this->count;
    }
    // Countable End

    // ArrayAccess Start
    public function offsetGet($id)
    {
        $this->fetch();
        if ($this->count > 0) {
            return $this->object($id);
        }
    }

    public function offsetUnset($id)
    {
        $this->fetch();
        unset($this->objects[$id]);
        $this->count = count($this->objects);
        if ($this->current_id == $id) {
            $this->current_id = key($this->objects);
        }
    }

    public function offsetSet($id, $object)
    {
        $this->fetch();
        if ($object->id) {
            $id = $object->id;
        }
        $this->objects[$id] = $object;
        $this->current_id = $id;
        $this->count = count($this->objects);
    }

    public function offsetExists($id)
    {
        $this->fetch();

        return isset($this->objects[$id]);
    }

    // ArrayAccess End
    public function prepend($object)
    {
        $this->fetch();

        if ($object->id) {
            $this->objects = [$object->id => $object] + $this->objects;
        }

        $this->count = count($this->objects);

        return $this;
    }

    public function reverse()
    {
        $this->fetch();
        //反排
        $this->objects = array_reverse($this->objects);

        return $this;
    }

    public function get($key = 'id', $val = null)
    {
        $this->fetch();

        $arr = array();
        if ($val === null) {
            foreach (array_keys($this->objects) as $k) {
                $o = $this->object($k);
                $arr[$o->id] = $o->$key;
            }
        } else {
            foreach (array_keys($this->objects) as $k) {
                $o = $this->object($k);
                $arr[$o->$key] = $o->$val;
            }
        }

        return $arr;
    }

    public function keys()
    {
        $this->fetch();
        return array_keys($this->objects);
    }

    public function forUpdate($forUpdate = true)
    {
        $this->_forUpdate = !!$forUpdate;
        return $this;
    }
}
