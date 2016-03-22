<?php

/**
 * Those ORM
 * $object = new \Gini\ORM\Object($id|[criteria array]);.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2014-01-27
 **/

/**
 * Define DocBlock.
 **/
namespace Gini;

abstract class ORM
{
    private static $_injections;
    private $_criteria;
    private $_objects;
    private $_name;
    private $_tableName;
    private $_oinfo;

    protected $_db_data;
    protected $_db_time;    //上次数据库同步的时间

    /**
     * Magic method to use Event('orm[$name].call[$method]') to extend ORM object.
     *
     * @param string $method
     * @param string $params
     *
     * @return mixed return value of the event
     */
    public function __call($method, $params)
    {
        if ($method == __FUNCTION__) {
            return;
        }
        /*
        orm[user].call[method]
        */
        $name = "call[$method]";
        if (!$this->event('isBinded', $name)) {
            $trace = debug_backtrace();
            $message = sprintf('[E]: Call to undefined method %s::%s() in %s on line %d',
                                $trace[1]['class'],
                                $trace[1]['function'],
                                $trace[1]['file'],
                                $trace[1]['line']);
            trigger_error($message, E_USER_ERROR);

            return;
        }

        return $this->event('trigger', $name, $params);
    }

    private function event()
    {
        $args = func_get_args();
        $func = array_shift($args);
        $action = array_shift($args);

        $inheritance = $this->inheritance();

        $events = [];
        foreach (array_keys($inheritance) as $name) {
            $events[] = "orm[$name].$action";
        }

        array_unshift($args, implode(' ', $events), $this);

        return call_user_func_array('\Gini\Event::'.$func, $args);
    }

    /**
     * Return inheritance of the ORM class.
     *
     * @return array
     */
    public function inheritance()
    {
        $inheritance = [];

        $class = get_class($this);
        $name = strtolower(implode('', array_slice(explode('\\', $class), -1)));
        $inheritance[$name] = $class;

        foreach (class_parents($this) as $class) {
            $name = strtolower(implode('', array_slice(explode('\\', $class), -1)));
            $inheritance[$name] = $class;
            if ($name == 'object') {
                break;
            }
        }

        return $inheritance;
    }

    public function properties()
    {
        $rc = new \ReflectionClass($this);
        $defaults = $rc->getDefaultProperties();

        $properties = [];
        foreach ($rc->getProperties() as $p) {
            if (!$p->isStatic() && $p->isPublic()) {
                $k = $p->getName();
                $properties[$k] = $defaults[$k];
            }
        }

        //check all injections
        foreach ((array) self::$_injections as $injection) {
            $rc = new \ReflectionClass($injection);
            $defaults = $rc->getDefaultProperties();

            foreach ($rc->getProperties() as $p) {
                if (!$p->isStatic() && $p->isPublic()) {
                    $k = $p->getName();
                    $properties[$k] = $defaults[$k];
                }
            }
        }

        return $properties;
    }

    private static $_structures;
    public function structure()
    {
        $class_name = get_class($this);
        if (!isset(self::$_structures[$class_name])) {
            $properties = $this->properties();
            $structure = [];
            foreach ($properties as $k => $v) {
                $params = explode(',', strtolower($v));
                $v = [];
                foreach ($params as $p) {
                    list($p, $pv) = explode(':', trim($p), 2);
                    $v[$p] = $pv;
                }

                $structure[$k] = $v;
            }
            self::$_structures[$class_name] = $structure;
        }

        return self::$_structures[$class_name];
    }

    public function fetch($force = false)
    {
        if ($force || $this->_db_time == 0) {
            if (is_array($this->_criteria) && count($this->_criteria) > 0) {
                $db = $this->db();

                $criteria = $this->normalizeCriteria($this->_criteria);

                //从数据库中获取该数据
                foreach ($criteria as $k => $v) {
                    $where[] = $db->quoteIdent($k).'='.
                        (($v instanceof \Gini\Those\SQL) ? strval($v) : $db->quote($v));
                }

                $schema = $this->schema();

                $fields = array_map([$db, 'quoteIdent'], array_keys($schema['fields']));

                $SQL = 'SELECT '.implode(', ', $fields)
                    .' FROM '.$db->quoteIdent($this->tableName())
                    .' WHERE '.implode(' AND ', $where).' LIMIT 1';

                $result = $db->query($SQL);
                //只取第一条记录
                if ($result) {
                    $data = $result->row(\PDO::FETCH_ASSOC);
                }
            }

            //给object赋值
            $this->setData((array) $data);
        }

        return $this;
    }

    public function __construct($criteria = null)
    {
        $structure = $this->structure();
        foreach ($structure as $k => $v) {
            unset($this->$k);    //empty all public properties
        }

        if ($criteria) {
            $this->criteria($criteria);
        }
    }

    public function db()
    {
        return Database::db(static::$db_name);
    }

    public function normalizeCriteria(array $crit)
    {
        $ncrit = [];
        $structure = $this->structure();

        foreach ($crit as $k => $v) {
            if (is_scalar($v) || is_null($v)) {
                $ncrit[$k] = $v;
            } elseif ($v instanceof \Gini\ORM\Object) {
                if (!isset($structure[$k]['object'])) {
                    $ncrit[$k.'_name'] = $v->name();
                }
                $ncrit[$k.'_id'] = $v->id;
            }
        }

        return $ncrit;
    }

    public function criteria($criteria = null)
    {
        if ($criteria !== null) {
            //set criteria
            if (is_scalar($criteria)) {
                $criteria = ['id' => (int) $criteria];
            }
            $this->_criteria = (array) $criteria;
        }

        return $this->_criteria;
    }

    public function schema()
    {
        $structure = $this->structure();

        $fields = [];
        $indexes = [];
        $relations = [];

        foreach ($structure as $k => $v) {
            $field = null;
            $index = null;

            foreach ($v as $p => $pv) {
                switch ($p) {
                case 'int':
                case 'bigint':
                case 'double':
                    $field['type'] = $p;
                    break;
                case 'datetime':
                    $field['type'] = $p;
                    $field['default'] = '0000-00-00 00:00:00';
                    break;
                case 'timestamp':
                    $field['type'] = $p;
                    $field['default'] = 'CURRENT_TIMESTAMP';
                    break;
                case 'bool':
                    $field['type'] = 'int';
                    break;
                case 'string':
                    if ($pv == '*') {
                        $field['type'] = 'text';
                    } else {
                        $field['type'] = 'varchar('.($pv ?: 255).')';
                    }
                    break;
                case 'array':
                case 'object_list':
                    $field['type'] = 'text';
                    break;
                case 'null':
                    $field['null'] = true;
                    break;
                case 'default':
                    $field['default'] = $pv;
                    break;
                case 'primary':
                    $indexes['PRIMARY'] = ['type' => 'primary', 'fields' => [$k]];
                    break;
                case 'unique':
                    $indexes['_IDX_'.$k] = ['type' => 'unique', 'fields' => [$k]];
                    break;
                case 'serial':
                    $field['serial'] = true;
                    break;
                case 'index':
                    $indexes['_IDX_'.$k] = ['fields' => [$k]];
                case 'object':
                    // 需要添加新的$field
                    if (!$pv) {
                        $fields[$k.'_name'] = ['type' => 'varchar(120)'];
                    }
                    $fields[$k.'_id'] = ['type' => 'bigint', 'null' => true];
                }
            }

            if ($field) {
                $fields[$k] = $field;
            }
        }

        $db_relation = static::$db_relation;
        if (count($db_relation) > 0) {
            foreach ($db_relation as $k => $v) {
                $params = explode(',', strtolower($v));
                $vv = [];
                foreach ($params as $p) {
                    list($p, $pv) = explode(':', trim($p), 2);
                    $vv[$p] = $pv;
                }

                $vvv = [];
                $vvv['delete'] = $vv['delete'];
                $vvv['update'] = $vv['update'];

                // correct object name
                if (array_key_exists('object', (array) $structure[$k])) {
                    if (!$structure[$k]['object']) {
                        continue;
                    }
                    $vvv['column'] = $k.'_id';
                    $vvv['ref_table'] = a($structure[$k]['object'])->tableName();
                    $vvv['ref_column'] = 'id';
                } elseif ($vv['ref']) {
                    $ref = explode('.', $vv['ref'], 2);
                    $vvv['ref_table'] = a($ref[0])->tableName();
                    $vvv['ref_column'] = $ref[1];
                } else {
                    // no ref? ignore this...
                    continue;
                }

                $relations[$this->tableName().'_'.$k] = $vvv;
            }
        }

        $db_index = static::$db_index;
        if (count($db_index) > 0) {
            // 索引项
            foreach ($db_index as $k => $v) {
                list($vk, $vv) = explode(':', $v, 2);
                $vk = trim($vk);
                $vv = trim($vv);
                if (!$vv) {
                    $vv = trim($vk);
                    $vk = null;
                }

                $vv = explode(',', $vv);
                foreach ($vv as &$vvv) {
                    $vvv = trim($vvv);
                    // correct object name
                    if (array_key_exists('object', (array) $structure[$vvv])) {
                        if (!$structure[$vvv]['object']) {
                            $vv[] = $vvv.'_name';
                        }
                        $vvv = $vvv.'_id';
                    }
                }

                switch ($vk) {
                case 'unique':
                    $indexes['_MIDX_'.$k] = ['type' => 'unique', 'fields' => $vv];
                    break;
                case 'primary':
                    $indexes['PRIMARY'] = ['type' => 'primary', 'fields' => $vv];
                    break;
                case 'fulltext':
                    $indexes['_MIDX_'.$k] = ['type' => 'fulltext', 'fields' => $vv];
                    break;
                default:
                    $indexes['_MIDX_'.$k] = ['fields' => $vv];
                }
            }
        }

        return ['fields' => $fields, 'indexes' => $indexes, 'relations' => $relations];
    }

    public function delete()
    {
        if (!$this->id) {
            return true;
        }

        $db = $this->db();
        $tbl_name = $this->tableName();

        $SQL = 'DELETE FROM '.$db->quoteIdent($tbl_name).' WHERE '.$db->quoteIdent('id').'='.$db->quote($this->id);

        return (bool) $db->query($SQL);
    }

    public function save()
    {
        $schema = (array) $this->schema();

        $db = $this->db();

        $success = false;

        $structure = $this->structure();

        $db_data = [];
        foreach ($structure as $k => $v) {
            if (array_key_exists('object', $v)) {
                $oname = $v['object'];
                if ($this->_objects[$k]) {
                    $o = $this->_objects[$k];
                    if (!isset($oname)) {
                        $db_data[$k.'_name'] = $oname ?: $o->name();
                    }
                } else {
                    $o = $this->_oinfo[$k];
                    if (!isset($oname)) {
                        $db_data[$k.'_name'] = $oname ?: $o->name;
                    }
                }
                $db_data[$k.'_id'] = $o->id ?: null;
            } elseif (array_key_exists('array', $v)) {
                $db_data[$k] = isset($this->$k) ? J($this->$k) : '{}';
            } elseif (array_key_exists('object_list', $v)) {
                $db_data[$k] = isset($this->$k) ? J($this->$k->keys()) : '[]';
            } else {
                $db_data[$k] = $this->$k;
                if (is_null($db_data[$k]) && !array_key_exists('null', $v)) {
                    $default = $v['default'];
                    if (is_null($default)) {
                        if (isset($v['string'])) {
                            $default = '';
                        } elseif (isset($v['datetime']) || isset($v['timestamp'])) {
                            $default = '0000-00-00 00:00:00';
                        } elseif (isset($v['int']) || isset($v['bigint']) || isset($v['double'])) {
                            $default = 0;
                        }
                    }

                    if (!is_null($default)) {
                        $db_data[$k] = $default;
                    }
                }
            }
        }

        // diff db_data and this->_db_data
        $db_data = array_diff_assoc((array) $db_data, (array) $this->_db_data);

        $tbl_name = $this->tableName();
        $id = (int) ($this->_db_data['id'] ?: $db_data['id']);
        unset($db_data['id']);

        if ($id > 0) {
            foreach ($db_data as $k => $v) {
                $pair[] = $db->quoteIdent($k).'='.
                   (($v instanceof \Gini\Those\SQL) ? strval($v) : $db->quote($v));
            }

            if ($pair) {
                $SQL = 'UPDATE '.$db->quoteIdent($tbl_name).' SET '.implode(',', $pair).' WHERE '.$db->quoteIdent('id').'='.$db->quote($id);
            }
        } else {
            $db_data = array_filter($db_data, function ($v) { return isset($v); });
            $keys = array_keys($db_data);
            $vals = array_values($db_data);
            $quoted_vals = array_map(function ($v) use ($db) {
                return ($v instanceof \Gini\Those\SQL) ? strval($v) : $db->quote($v);
            }, $vals);
            $SQL = 'INSERT INTO '.$db->quoteIdent($tbl_name).' ('.$db->quoteIdent($keys).') VALUES('.implode(', ', $quoted_vals).')';
        }

        if ($SQL) {
            $success = (bool) $db->query($SQL);
        } else {
            $success = true;
        }

        if ($success) {
            if (!$id) {
                $id = $db->lastInsertId();
            }

            $this->criteria($id);
            $this->fetch(true);
        }

        return $success;
    }

    /**
     * Inject structure declaration to current class.
     *
     * @param array $injection
     */
    public static function inject(array $injection)
    {
        self::$_injections[] = $injection;
        // clear structure cache
        unset(self::$_structures[get_called_class()]);
    }

    private function _prepareName()
    {
        // remove Gini/ORM
        list(, , $name) = explode('/', str_replace('\\', '/', strtolower(get_class($this))), 3);
        $this->_name = $name;
        $this->_tableName = str_replace('/', '_', $name);
    }

    /**
     * Return object name.
     */
    public function name()
    {
        if (!isset($this->_name)) {
            $this->_prepareName();
        }

        return $this->_name;
    }

    /**
     * Return corresponding table name of the object.
     *
     * @return string
     */
    public function tableName()
    {
        if (!isset($this->_tableName)) {
            $this->_prepareName();
        }

        return $this->_tableName;
    }

    /**
     * Set raw data of the object.
     *
     * @param string $data
     */
    public function setData(array $data)
    {
        $this->_db_data = $data;
        $this->_db_time = time();

        $this->_objects = [];
        $this->_oinfo = [];

        foreach ($this->structure() as $k => $v) {
            if (array_key_exists('object', $v)) {
                $oname = $v['object'];
                $o = $data[$k];
                if (isset($o) && $o instanceof \Gini\ORM\Object && isset($oname) && $o->name() == $oname) {
                    $this->$k = $o;
                } else {
                    //object need to be bind later to avoid deadlock.
                    unset($this->$k);
                    if (!isset($oname)) {
                        $oname = strval($data[$k.'_name']);
                    }
                    if ($oname) {
                        $oi = (object) ['name' => $oname, 'id' => $data[$k.'_id']];
                        $this->_oinfo[$k] = $oi;
                    }
                }
            } elseif (array_key_exists('array', $v)) {
                $this->$k = @json_decode(strval($data[$k]), true);
            } elseif (array_key_exists('object_list', $v)) {
                $objects = \Gini\IoC::construct('\Gini\ORMIterator', $v['object_list']);
                $oids = (array) @json_decode(strval($data[$k]), true);
                array_walk($oids, function ($id) use ($objects) {
                    $objects[$id] = true;
                });
                $this->$k = $objects;
            } else {
                $this->$k = $data[$k];
            }
        }

        return $this;
    }

    /**
     * Get raw data of the object.
     *
     * @return array
     */
    public function getData()
    {
        foreach ($this->structure() as $k => $v) {
            $data[$k] = $this->$k;
        }

        return $data;
    }

    public function &__get($name)
    {
        // 如果之前没有触发数据库查询, 在这里触发一下
        $this->fetch();

        if (isset($this->_objects[$name])) {
            return $this->_objects[$name];
        } elseif (isset($this->_oinfo[$name])) {
            $oi = $this->_oinfo[$name];
            $o = a($oi->name, $oi->id);
            $this->_objects[$name] = $o;

            return $o;
        } elseif (isset($this->_extra[$name])) {
            // try find it in _extra
            return $this->_extra[$name];
        }

        // 直接返回, 保证引用, 能够用于数字赋值
        if (isset($this->$name)) {
            return $this->$name;
        }

        // 以下返回值为了保证原始数据不被修改, 因此先用$ret复制后再返回

        // if $name is  {}_name or {}_id, let us get
        if (preg_match('/^(.+)_id$/', $name, $parts)) {
            $oname = $parts[1];
            if (isset($this->_objects[$oname])) {
                return $ret = $this->_objects[$oname]->id;
            }
        }

        if (isset($this->_db_data[$name])) {
            return $ret = $this->_db_data[$name];
        }
    }

    public function __set($name, $value)
    {
        // 如果之前没有触发数据库查询, 在这里触发一下
        $this->fetch();

        $structure = $this->structure();
        if (isset($this->_oinfo[$name])) {
            $this->_objects[$name] = $value;
        } elseif (isset($structure[$name])) {
            $this->$name = $value;
        } else {
            // if $name is  {}_name or {}_id, let's update oinfo firstly.
            $is_object = false;
            if (preg_match('/^(.+)_id$/', $name, $parts)) {
                $oname = $parts[1];
                if (isset($this->_oinfo[$oname])) {
                    $this->_oinfo[$oname]->id = $value;
                    $is_object = true;
                    unset($this->_objects[$oname]);
                }
            }

            if ($is_object == false) {
                // 奇怪 如果之前没有强制类型转换 数组赋值会不成功
                $this->_extra = (array) $this->_extra;
                $this->_extra[$name] = $value;
            }
        }
    }

    /**
     * Return localized string by property name.
     *
     * @param string $name
     * @param string $locale
     */
    public function L($name, $locale = null)
    {
        // 如果之前没有触发数据库查询, 在这里触发一下
        $this->fetch();

        // if \Gini\Config::get('system.locale') == 'zh_CN', $object->L('name') will access $object->_extra['i18n'][zh_CN]['name']
        if (!isset($locale)) {
            $locale = \Gini\Config::get('system.locale');
        }
        if (isset($this->_extra['@i18n'][$locale][$name])) {
            return $this->_extra['@i18n'][$locale][$name];
        }

        return $this->$name;
    }
}
