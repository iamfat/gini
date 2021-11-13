<?php

namespace Gini\Those;

use Gini\Those;

class Whose implements Condition
{
    protected $field;
    protected $op;
    protected $params;

    public function __construct($field)
    {
        $this->field = $field;
    }

    public function isIn($value)
    {
        $this->op = 'in';
        $this->params = $value;
        return $this;
    }

    public function isNotIn($value)
    {
        $this->op = 'not in';
        $this->params = $value;
        return $this;
    }

    public function isRelatedTo($value)
    {
        $this->op = 'match';
        $this->params = $value;
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
        $this->op = 'between';
        $this->params = [$a, $b];
        return $this;
    }

    public function match($op, $value)
    {
        switch ($op) {
            case '^=':
                $this->op = 'like';
                $this->params = $value . '%';
                break;

            case '$=':
                $this->op = 'like';
                $this->params = '%' . $value;
                break;

            case '*=':
                $this->op = 'like';
                $this->params = '%' . $value . '%';
                break;

            case '=':
            case '==':
                $this->op = '=';
                $this->params = $value;
                break;

            case '<>':
                $this->op = '<>';
                $this->params = $value;
                break;
                break;

            default:
                $this->op = $op;
                $this->params = $value;
                break;
        }
        return $this;
    }

    public static function fieldName(Those $those, $field, $options = null)
    {
        $name = $those->name();
        $db = $those->db();

        // table1.table2.table3.field
        $fields = explode('.', $field);
        $key = '';

        // skip duplicated parts
        while (current($fields) === $name) {
            array_shift($fields);
        }

        if (is_string($options)) {
            $suffix = $options;
            $options = ['suffix' => $options];
        } else {
            $options = $options ?? [];
        }

        $suffix = $options['suffix'] ?? null;
        switch ($options['join'] ?? null) {
            case 'inner':
                $JOIN_OP = 'INNER JOIN ';
                break;
            default:
                $JOIN_OP = 'LEFT JOIN ';
        }

        while ($name) {
            // chop field one by one
            $field = array_shift($fields);
            $fieldKey = $key ? $key . '.' . $field : $field;

            $joinedTables = $those->context('joined-tables');
            $join = $those->context('join');
            $table = $key ? $joinedTables[$key] : $those->context('current-table');

            $o = a($name);
            $structure = $o->structure();
            $manyStructure = $o->manyStructure();
            if (isset($manyStructure[$field])) {
                // it is a many-field, a pivot table required
                $pivotName = $o->pivotTableName($field);
                $pivotKey = "$fieldKey@pivot";
                if (!isset($join[$pivotKey])) {
                    $joinedTables[$pivotKey] = $pivotTable = 't' . $those->uniqid();
                    $join[$pivotKey] = $JOIN_OP . $db->ident($pivotName)
                        . ' AS ' . $db->quoteIdent($pivotTable)
                        . ' ON ' . $db->ident($pivotTable, $name . '_id') . '=' . $db->ident($table, 'id');
                    $those->context('joined-tables', $joinedTables);
                    $those->context('join', $join);
                }
            } else {
                $pivotName = null;
            }

            if (count($fields) == 0 && $field && $suffix !== null) {
                if ($pivotName) {
                    $pivotKey = "$fieldKey@pivot";
                    $pivotTable = $joinedTables[$pivotKey];
                    return $db->ident($pivotTable, $field . $suffix);
                } else {
                    return $db->ident($table, $field . $suffix);
                }
            }

            if ($pivotName) {
                if (isset($manyStructure[$field]['object'])) {
                    $fieldName = $manyStructure[$field]['object'];
                    $tableName = a($fieldName)->tableName();
                    if (!isset($joinedTables[$fieldKey])) {
                        $joinedTables[$fieldKey] = $fieldTable = 't' . $those->uniqid();
                        $pivotKey = "$fieldKey@pivot";
                        $pivotTable = $joinedTables[$pivotKey];
                        $join[$fieldKey] = $JOIN_OP . $db->ident($tableName)
                            . ' AS ' . $db->quoteIdent($fieldTable)
                            . ' ON ' . $db->ident($pivotTable, $field . '_id') . '=' . $db->ident($fieldTable, 'id');
                        $those->context('joined-tables', $joinedTables);
                        $those->context('join', $join);
                    }
                } else {
                    $pivotKey = "$fieldKey@pivot";
                    $pivotTable = $joinedTables[$pivotKey];
                    return $db->ident($pivotTable, $field);
                }
            } elseif (isset($structure[$field])) {
                if (isset($structure[$field]['object'])) {
                    $fieldName = $structure[$field]['object'];
                    if ($fieldName) {
                        $tableName = a($fieldName)->tableName();
                        if (!isset($joinedTables[$fieldKey])) {
                            $joinedTables[$fieldKey] = $fieldTable = 't' . $those->uniqid();
                            $join[$fieldKey] = $JOIN_OP . $db->ident($tableName)
                                . ' AS ' . $db->quoteIdent($fieldTable)
                                . ' ON ' . $db->ident($table, $field . '_id') . '=' . $db->ident($fieldTable, 'id');
                            $those->context('joined-tables', $joinedTables);
                            $those->context('join', $join);
                        }
                    }
                } else {
                    return $db->ident($table, $field);
                }
            } elseif (count($fields) == 0) {
                return $db->ident($table, ($field ?: 'id'));
            }

            $name = $fieldName;
            $key = $fieldKey;
        }
    }

    public static function fieldValue(Those $those, $v)
    {
        if ($v instanceof \Gini\Database\SQL) {
            return strval($v);
        }

        $db = $those->db();
        if (preg_match('/^@(?:(\w+)\.)?(\w+)$/', $v, $parts)) {
            //有可能是某个table的field名
            list(, $table, $field) = $parts;
            if ($table) {
                $alias = $those->context('alias');
                while ($alias[$table]) {
                    $table = $alias[$table];
                }
            } else {
                $table = $those->context('current-table');
            }

            return $db->ident($table, $field);
        }

        return $db->quote($v);
    }


    public function createWhere(Those $those)
    {
        $db = $those->db();
        $where = false;

        switch ($this->op) {
            case 'like':
                $fieldName = self::fieldName($those, $this->field);
                $where = $fieldName . ' LIKE ' . $db->quote($this->params);
                break;
            case '=':
            case '<>':
                if ($this->params instanceof \Gini\ORM\Base) {
                    $o = a($those->name());
                    $field = $this->field;
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
                        $obj_where[] = self::fieldName($those, $field, '_name') . $this->op . $db->quote($this->params->name());
                    }
                    $obj_where[] = self::fieldName($those, $field, '_id') . $this->op . intval($this->params->id);
                    if ($this->op == '<>') {
                        $where = Those::packWhere($obj_where, 'OR');
                    } else {
                        $where = Those::packWhere($obj_where, 'AND');
                    }
                } elseif (is_null($this->params)) {
                    if ($this->op == '<>') {
                        $where =  self::fieldName($those, $this->field)  . ' IS NOT NULL';
                    } else {
                        // if field is object, we should not add inner join
                        $where = self::fieldName($those, $this->field) . ' IS NULL';
                    }
                } else {
                    $where = self::fieldName($those, $this->field) . $this->op . self::fieldValue($those, $this->params);
                }
                break;
            case 'between':
                $fieldName = self::fieldName($those, $this->field);
                $where = '(' . $fieldName . '>=' . self::fieldValue($those, $this->params[0]) .
                    ' AND ' . $fieldName . '<' . self::fieldValue($those, $this->params[1]) . ')';
                break;
            case 'in':
            case 'not in':
                if ($this->params instanceof \Gini\Those) {
                    $v = $this->params;
                    $v->finalizeCondition();

                    $join = [];
                    $join[] =  'LEFT JOIN ' . $db->ident($v->tableName()) . ' AS ' . $db->quoteIdent($v->context('current-table'))
                        . ' ON ' . self::fieldName($those, $this->field, '_id') . '=' . $db->ident($v->context('current-table'), 'id');
                    $v_join = $v->context('join');
                    if ($v_join) {
                        $join = array_merge($join, $v_join);
                    }

                    $v_where = $v->context('where');
                    if ($v_where) {
                        $join = array_merge($join, ['AND'], $v_where);
                    }

                    $those->context('join', array_merge((array)$those->context('join'), $join));
                    if ($this->op == 'in') {
                        $where = $db->ident($v->context('current-table'), 'id') . ' IS NOT NULL';
                    } else {
                        $where = $db->ident($v->context('current-table'), 'id') . ' IS NULL';
                    }
                } else {
                    $qv = [];
                    foreach ($this->params as $v) {
                        $qv[] = $db->quote($v);
                    }
                    if (count($qv) > 0) {
                        $where = self::fieldName($those, $this->field) .
                        ($this->op == 'in' ? '' : ' NOT') . ' IN (' . implode(', ', $qv) . ')';
                    } else {
                        $where = $this->op == 'in' ? '1 = 0' : '1 = 1';
                    }
                }
                break;
            default:
                $where = self::fieldName($those, $this->field) . $this->op . self::fieldValue($those, $this->params);
        }

        return $where;
    }
}
