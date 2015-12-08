<?php

namespace Gini\Those;

class SQLHelper
{
    protected $db;

    protected $name;
    protected $table_name;

    private $_table;
    private $_field;
    private $_where;
    private $_join;
    private $_alias;

    private static $_uniqid = 0;
    public function uniqid()
    {
        return self::$_uniqid++;
    }

    public static function reset()
    {
        self::$_uniqid = 0;
    }

    public function __construct($name)
    {
        $name = \Gini\Config::get('orm.plurals')[$name] ?: $name;
        $this->name = $name;
        $this->table_name = str_replace('/', '_', $name);
        $this->db = a($name)->db();

        $this->_table = 't'.$this->uniqid();
    }

    private function _getValue($v, $raw = false)
    {
        if ($raw) {
            return $v;
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
            $where = array($where);
        }
        if (count($where) <= 1) {
            return $where[0];
        }

        return '('.implode(' '.$op.' ', $where).')';
    }

    public function limit($start, $per_page = null)
    {
        if ($per_page > 0) {
            $this->_limit = sprintf('%d, %d', $start, $per_page);
        } else {
            $this->_limit = sprintf('%d', $start);
        }

        return $this;
    }

    public function whose($field)
    {
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
        $this->_where[] = 'OR';
        $this->_field = $field;

        return $this;
    }

    public function whoIs($field)
    {
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
        $this->_alias[$name] = $this->_table;

        return $this;
    }

    public function isIn()
    {
        $values = func_get_args();

        $db = $this->db;
        $field_name = $db->ident($this->_table, $this->_field);

        $v = reset($values);
        if ($v instanceof self) {
            $field_name = $db->ident($this->_table, $this->_field.'_id');
            $this->_join[] = 'INNER JOIN '.$db->ident($v->table_name).' AS '.$db->quoteIdent($v->_table)
                .' ON '.$field_name.'='.$db->ident($v->_table, 'id');
            if ($v->_join) {
                $this->_join = array_merge($this->_join, $v->_join);
            }
            if ($v->_where) {
                $this->_where = array_merge((array) $this->_where, $v->_where);
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
        $field_name = $db->ident($this->_table, $this->_field);

        $v = reset($values);
        if ($v instanceof self) {
            $field_name = $db->ident($this->_table, $this->_field.'_id');
            $this->_join[] = 'LEFT JOIN '.$db->ident($v->table_name).' AS '.$db->quoteIdent($v->_table)
                .' ON '.$field_name.'='.$db->ident($v->_table, 'id');
            if ($v->_join) {
                $this->_join = array_merge($this->_join, $v->_join);
            }
            if ($v->_where) {
                $this->_where = array_merge((array) $this->_where, $v->_where);
                $this->_where[] = 'AND';
            }
            $this->_where[] = $field_name.' IS NOT NULL';
        } else {
            foreach ($values as $v) {
                $qv[] = $db->quote($v);
            }
            $this->_where[] = $field_name.' NOT IN ('.implode(', ', $qv).')';
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
                    if (array_key_exists('object', $structure[$field])) {
                        if (!$structure[$field]['object']) {
                            $obj_where[] = $db->ident($this->_table, $field.'_name').$op.$db->quote($v->name());
                        }

                        $obj_where[] = $db->ident($this->_table, $field.'_id').$op.intval($v->id);

                        if ($op == '<>') {
                            $this->_where[] = $this->_packWhere($obj_where, 'OR');
                        } else {
                            $this->_where[] = $this->_packWhere($obj_where, 'AND');
                        }
                        break;
                    }
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
                $this->_where[] = $field_name.$op.$this->_getValue($v, $raw);
            }

        }

        return $this;
    }

    // is(1), is('hello'), is('@name')
    public function is($v, $raw = false)
    {
        return $this->match('=', $v, $raw);
    }

    public function isNot($v, $raw = false)
    {
        return $this->match('<>', $v, $raw);
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

    public function isLessThan($v, $raw = false)
    {
        return $this->match('<', $v, $raw);
    }

    public function isGreaterThan($v, $raw = false)
    {
        return $this->match('>', $v, $raw);
    }

    public function isGreaterThanOrEqual($v, $raw = false)
    {
        return $this->match('>=', $v, $raw);
    }

    public function isLessThanOrEqual($v, $raw = false)
    {
        return $this->match('<=', $v, $raw);
    }

    public function isBetween($a, $b, $raw = false)
    {
        assert($this->_field);
        $db = $this->db;
        $field_name = $db->ident($this->_table, $this->_field);
        $this->_where[] = '('.$field_name.'>='.$this->_getValue($a, $raw).
            ' AND '.$field_name.'<'.$this->_getValue($b, $raw).')';

        return $this;
    }

    public function orderBy($field, $mode = 'asc')
    {
        $db = $this->db;
        $mode = strtolower($mode);
        switch ($mode) {
            case 'desc':
            case 'd':
            $this->_order_by[] = $db->ident($this->_table, $field).' DESC';
            break;
            case 'asc':
            case 'a':
            $this->_order_by[] = $db->ident($this->_table, $field).' ASC';
            break;
        }

        return $this;
    }

    public function where(array $where)
    {
        $this->_where = count($where) > 1 ? ['('.implode(') AND (', $where).')'] : $where;

        return $this;
    }

    public function fragment()
    {
        $db = $this->db;
        $table = $this->_table;

        if ($this->_where) {
            $SQL = implode(' ', $this->_where);
        }

        unset($this->_join);
        unset($this->_where);
        unset($this->_order_by);
        unset($this->_limit);

        return $SQL;
    }

    public function finalize()
    {
        $db = $this->db;
        $table = $this->_table;

        $from_SQL = 'FROM '.$db->ident($this->table_name).' AS '.$db->quoteIdent($this->_table);

        if ($this->_join) {
            $from_SQL .= ' '.implode(' ', $this->_join);
        }

        if ($this->_where) {
            $from_SQL .= ' WHERE '.implode(' ', $this->_where);
        }

        if ($this->_order_by) {
            $order_SQL = 'ORDER BY '.implode(', ', $this->_order_by);
        }

        if ($this->_limit) {
            $limit_SQL = 'LIMIT '.$this->_limit;
        }

        $id_col = $db->ident($table, 'id');
        $SQL = trim("SELECT DISTINCT $id_col $from_SQL $order_SQL $limit_SQL");

        unset($this->_join);
        unset($this->_where);
        unset($this->_order_by);
        unset($this->_limit);

        return $SQL;
    }

    public function tableAlias()
    {
        return $this->_table;
    }

    public function table()
    {
        return $this->table_name;
    }

    public function db()
    {
        return $this->db;
    }
}
