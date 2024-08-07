<?php

/**
 * Those ORM
 * $object = new \Gini\ORM\Base($id|[criteria array]);.
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

namespace Gini {

    #[\AllowDynamicProperties]
    abstract class ORM
    {
        private $_criteria;
        private $_objects;
        private $_manyFields;
        private $_name;
        private $_tableName;
        private $_oinfo;
        protected $_forUpdate = false;
        // 如果指定id向数据库新插入一条记录的时候，是否用 replace into 代替 insert into
        // replace into 会检测是否有符合条件的unique约束的行，如果有，直接更新改行的id
        // insert into 直接插入，如果检测到有符合unique约束的行，插入将失败
        protected $_forReplace = true;

        protected $_db_data;
        protected $_db_time; //上次数据库同步的时间

        private static $_STRUCTURES;
        private static $_MANY_STRUCTURES;
        private static $_RELATIONS;
        private static $_INDEXES;

        private static $_INJECTIONS;

        private $autocast = false;

        protected static $db_name;
        protected static $db_relation = [];
        protected static $db_index = [];

        /**
         * Magic method to use Event('orm[$name].call[$method]') to extend ORM object.
         *
         * @param string $method
         * @param array $params
         *
         * @return mixed return value of the event
         */
        public function __call($method, $params)
        {
            if ($method === __FUNCTION__) {
                return;
            }
            /*
        orm[user].call[method]
         */
            $name = "call[$method]";
            if (!$this->event('isBinded', $name)) {
                throw new \BadMethodCallException();
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

            array_unshift($args, $events, $this);

            return call_user_func_array('\Gini\Event::' . $func, $args);
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
                if ($name == 'base' || $name == 'object') {
                    break;
                }
            }

            return $inheritance;
        }

        public function ownProperties()
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

            return $properties;
        }

        public function properties()
        {
            $properties = $this->ownProperties();
            //check all injections
            $class_name = get_class($this);
            foreach (self::$_INJECTIONS[$class_name] ?? [] as $injection) {
                $properties = array_merge($properties, (array) $injection['properties']);
            }

            return $properties;
        }

        private function _structureFromProperties($properties)
        {
            $structure = [];
            $manyStructure = [];
            foreach ((array) $properties as $k => $v) {
                $params = explode(',', strtolower($v));
                $v = [];
                foreach ($params as $p) {
                    $pkv = array_map('trim', explode(':', $p));
                    $v[$pkv[0]] = isset($pkv[1]) ? $pkv[1] : null;
                }

                if (array_key_exists('many', $v)) {
                    $manyStructure[$k] = $v;
                } else {
                    $structure[$k] = $v;
                }
            }
            return [$structure, $manyStructure];
        }

        private function _prepareStructures($className)
        {
            $properties = $this->properties();
            list($structure, $manyStructure) = $this->_structureFromProperties($properties);
            self::$_STRUCTURES[$className] = $structure;
            self::$_MANY_STRUCTURES[$className] = $manyStructure;
        }

        public function structure()
        {
            $className = get_class($this);
            if (!isset(self::$_STRUCTURES[$className])) {
                $this->_prepareStructures($className);
            }
            return self::$_STRUCTURES[$className];
        }

        public function manyStructure()
        {
            $className = get_class($this);
            if (!isset(self::$_MANY_STRUCTURES[$className])) {
                $this->_prepareStructures($className);
            }
            return self::$_MANY_STRUCTURES[$className];
        }

        public function fetch($force = false)
        {
            if ($force || $this->_db_time == 0) {
                $data = null;
                if ($this->_criteria) {
                    $db = $this->db();
                    if ($this->_criteria instanceof Database\SQL) {
                        $SQL = (string) $this->_criteria;
                    } elseif (is_array($this->_criteria) && count($this->_criteria) > 0) {
                        $criteria = $this->normalizeCriteria($this->_criteria);

                        //从数据库中获取该数据
                        foreach ($criteria as $k => $v) {
                            $where[] = $db->quoteIdent($k) . '=' .
                                (($v instanceof Database\SQL) ? strval($v) : $db->quote($v));
                        }

                        $schema = $this->ormSchema();
                        $fields = array_map([$db, 'quoteIdent'], array_keys($schema['fields']));

                        $SQL = 'SELECT ' . implode(', ', $fields)
                            . ' FROM ' . $db->quoteIdent($this->tableName())
                            . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1';
                    }

                    if ($this->_forUpdate) {
                        $SQL .= ' FOR UPDATE';
                        $this->_forUpdate = false; // add FOR UPDATE for only once
                    }

                    $result = $db->query($SQL);
                    //只取第一条记录
                    if ($result) {
                        $data = $result->row(\PDO::FETCH_ASSOC);
                    }
                }

                //给object赋值
                $this->setData(is_array($data) ? $data : []);
            }

            return $this;
        }

        public function resetFetch()
        {
            // clean $_db_time to trigger later fetch
            $this->_db_time = 0;
            $this->_objects = [];
            $this->_manyFields = [];
            $this->_oinfo = [];
            foreach ($this->structure() as $k => $v) {
                unset($this->$k); //empty all public properties
            }
            $this->_forUpdate = true;
        }

        public function __construct($criteria = null)
        {
            $this->autocast = !!\Gini\Config::get('system.orm_autocast');
            $properties = $this->ownProperties();
            foreach ($properties as $k => $v) {
                unset($this->$k); //empty all public properties
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
                } elseif ($v instanceof \Gini\ORM\Base) {
                    if (!isset($structure[$k]['object'])) {
                        $ncrit[$k . '_name'] = $v->name();
                    }
                    $ncrit[$k . '_id'] = $v->id;
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
                $this->_criteria = $criteria;
            }

            return $this->_criteria;
        }

        public function ormRelations()
        {
            $class_name = get_class($this);
            if (!isset(self::$_RELATIONS[$class_name])) {
                $db_relation = static::$db_relation;
                //check all injections
                foreach (self::$_INJECTIONS[$class_name] ?? [] as $injection) {
                    $db_relation = array_merge($db_relation, (array) $injection['relations']);
                }

                $relations = [];
                foreach ($db_relation as $k => $v) {
                    $params = explode(',', strtolower($v));
                    $vv = [];
                    foreach ($params as $p) {
                        list($p, $pv) = explode(':', trim($p), 2);
                        $vv[$p] = $pv;
                    }
                    $relations[$k] = $vv;
                }
                self::$_RELATIONS[$class_name] = $relations;
            }

            return self::$_RELATIONS[$class_name];
        }

        // 'a', 'unique:b,c', 'd,e,f'
        public function ormIndexes()
        {
            $class_name = get_class($this);
            if (!isset(self::$_INDEXES[$class_name])) {
                $indexes = static::$db_index;
                //check all injections
                foreach (self::$_INJECTIONS[$class_name] ?? [] as $injection) {
                    $indexes = array_merge($indexes, (array) $injection['indexes']);
                }
                self::$_INDEXES[$class_name] = $indexes;
            }

            return self::$_INDEXES[$class_name];
        }

        public function schema()
        {
            return $this->ormSchema();
        }

        public function ormSchema($structure = null, $ormIndexes = null, $ormRelations = null, $table = null)
        {
            $structure = $structure ?: $this->structure();

            $fields = [];
            $indexes = [];
            $relations = [];

            foreach ($structure as $k => $v) {
                $field = null;
                $fieldKey = $this->normalizeName($k);
                foreach ($v as $p => $pv) {
                    switch ($p) {
                        case 'int':
                        case 'bigint':
                        case 'double':
                        case 'float':
                            $field['type'] = $p;
                            if ($pv) {
                                $field['type'] .= '(' . intval($pv) . ')';
                            }
                            break;
                        case 'decimal':
                            $field['type'] = $p;
                            if ($pv) {
                                $field['type'] .= '(' . strtr($pv, '.', ',') . ')';
                            }
                            break;
                        case 'datetime':
                            $field['type'] = $p;
                            break;
                        case 'timestamp':
                            $field['type'] = $p;
                            break;
                        case 'bool':
                            $field['type'] = 'int';
                            break;
                        case 'string':
                            if ($pv == '*') {
                                $field['type'] = 'text';
                            } elseif ($pv == '**') {
                                $field['type'] = 'mediumtext';
                            } elseif ($pv == '***') {
                                $field['type'] = 'longtext';
                            } else {
                                $field['type'] = 'varchar(' . ($pv ?: 255) . ')';
                            }
                            break;
                        case 'array':
                        case 'object_list':
                            if ($pv == '**') {
                                $field['type'] = 'mediumtext';
                            } elseif ($pv == '***') {
                                $field['type'] = 'longtext';
                            } else {
                                $field['type'] = 'text';
                                $field['default'] = $field['default'] ?? '{}';
                            }
                            break;
                        case 'null':
                            $field['null'] = true;
                            break;
                        case 'default':
                            $field['default'] = $pv;
                            break;
                        case 'serial':
                            $field['serial'] = true;
                        case 'primary':
                            $indexes['PRIMARY'] = ['type' => 'primary', 'fields' => [$fieldKey]];
                            break;
                        case 'unique':
                            $indexes['_IDX_' . $fieldKey] = ['type' => 'unique', 'fields' => [$fieldKey]];
                            break;
                        case 'index':
                            $indexes['_IDX_' . $fieldKey] = ['fields' => [$fieldKey]];
                            // no break
                        case 'object':
                            // 需要添加新的$field
                            if (!$pv) {
                                $fields[$fieldKey . '_name'] = ['type' => 'varchar(120)'];
                            }
                            $fields[$fieldKey . '_id'] = ['type' => 'bigint', 'null' => true];
                    }
                }

                if ($field) {
                    if (preg_match('/^(\w+)(?:\((.+)\))?$/', $field['type'], $parts)) {
                        switch ($parts[1]) {
                            case 'int':
                            case 'bigint':
                                if (!isset($field['null']) && !isset($field['default'])) {
                                    $field['default'] = 0;
                                } else {
                                    $field['default'] = isset($field['default']) ? intval($field['default']) : 0;
                                }
                                break;
                            case 'double':
                            case 'float':
                            case 'decimal':
                                if (!isset($field['null']) && !isset($field['default'])) {
                                    $field['default'] = 0.0;
                                } else {
                                    $field['default'] = floatval($field['default']);
                                }
                                break;
                            case 'datetime':
                                if (!isset($field['null']) && !isset($field['default'])) {
                                    $field['default'] = '0000-00-00 00:00:00';
                                }
                                break;
                            case 'timestamp':
                                if (!isset($field['null']) && !isset($field['default'])) {
                                    $field['default'] = 'CURRENT_TIMESTAMP';
                                }
                                break;
                            case 'text':
                            case 'varchar':
                            case 'char':
                                if (!isset($field['null']) && !isset($field['default'])) {
                                    $field['default'] = '';
                                }
                                break;
                        }
                    }
                    $fields[$fieldKey] = $field;
                }
            }

            $ormRelations = $ormRelations ?: $this->ormRelations();
            foreach ($ormRelations as $k => $vv) {
                $vvv = [];
                $vvv['delete'] = $vv['delete'];
                $vvv['update'] = $vv['update'];

                $relationKey = $this->normalizeName($k);
                // correct object name
                if (array_key_exists('object', (array) $structure[$k])) {
                    if (!$structure[$k]['object']) {
                        continue;
                    }
                    $vvv['column'] = $relationKey . '_id';
                    $vvv['ref_table'] = a($structure[$k]['object'])->tableName();
                    $vvv['ref_column'] = 'id';
                } elseif (isset($vv['ref'])) {
                    $vvv['column'] = $relationKey;
                    $ref = explode('.', $vv['ref'], 2);
                    $vvv['ref_table'] = a($ref[0])->tableName();
                    $vvv['ref_column'] = $ref[1];
                } else {
                    // no ref? ignore this...
                    continue;
                }

                $prefix = $table ?: $this->tableName();
                $relations[$prefix . '_' . $relationKey] = $vvv;
            }

            // 索引项
            $ormIndexes = $ormIndexes ?: $this->ormIndexes();
            foreach ($ormIndexes as $k => $v) {
                list($vk, $vv) = array_pad(explode(':', $v, 2), 2, '');
                $vk = trim($vk);
                $vv = trim($vv);
                if (!$vv) {
                    $vv = $vk;
                    $vk = null;
                }
                $indexKey = $this->normalizeName($k);
                $vv = explode(',', $vv);
                foreach ($vv as &$vvv) {
                    $vvv = trim($vvv);
                    // correct object name
                    if (array_key_exists('object', (array) $structure[$vvv])) {
                        if (!$structure[$vvv]['object']) {
                            $vv[] = $vvv . '_name';
                        }
                        $vvv = $vvv . '_id';
                    }
                    $vvv = $this->normalizeName($vvv);
                }

                switch ($vk) {
                    case 'unique':
                        $indexes['_MIDX_' . $indexKey] = ['type' => 'unique', 'fields' => $vv];
                        break;
                    case 'primary':
                        if (!isset($indexes['PRIMARY'])) {
                            $indexes['PRIMARY'] = ['type' => 'primary', 'fields' => $vv];
                        }
                        break;
                    case 'fulltext':
                        $indexes['_MIDX_' . $indexKey] = ['type' => 'fulltext', 'fields' => $vv];
                        break;
                    default:
                        $indexes['_MIDX_' . $indexKey] = ['fields' => $vv];
                }
            }

            return ['fields' => $fields, 'indexes' => $indexes, 'relations' => $relations];
        }

        public function ormAdditionalSchemas()
        {
            $schemas = [];
            $name = $this->name();

            foreach ((array) $this->manyStructure() as $k => $v) {
                $table = $this->pivotTableName($k);
                $schema = $this->ormSchema([
                    $name => ['object' => $name],
                    $k => $v,
                ], ["primary:$name,$k"], [
                    $name => ['update' => 'cascade', 'delete' => 'cascade'],
                    $k => ['update' => 'cascade', 'delete' => 'cascade'],
                ], $table);
                $schemas[$table] = $schema;
            }

            return $schemas;
        }

        public function forceDelete()
        {
            if (!$this->id) {
                return true;
            }

            $db = $this->db();
            $tbl_name = $this->tableName();

            $SQL = 'DELETE FROM ' . $db->quoteIdent($tbl_name)
                . ' WHERE "id"=' . $db->quote($this->id);

            return (bool) $db->query($SQL);
        }

        public function delete()
        {
            if (!$this->id) {
                return true;
            }

            if (is_callable($this, '_delete')) {
                return $this->_delete();
            }

            return $this->forceDelete();
        }

        public function dbData()
        {
            $db_data = [];
            foreach ($this->structure() as $k => $v) {
                if (array_key_exists('object', $v)) {
                    $oname = $v['object'];
                    if (isset($this->_objects[$k])) {
                        $o = $this->_objects[$k];
                        if (!isset($oname)) {
                            $db_data[$k . '_name'] = $oname ?: $o->name();
                        }
                    } else {
                        $o = $this->_oinfo[$k] ?? null;
                        if (isset($o) && !isset($oname)) {
                            $db_data[$k . '_name'] = $oname ?: $o->name;
                        }
                    }
                    $db_data[$k . '_id'] = $o ? $o->id ?? null : null;
                } elseif (array_key_exists('array', $v)) {
                    if (is_array($this->$k)) {
                        if (count($this->$k) === 0) {
                            $db_data[$k] = '{}';
                        } else {
                            $db_data[$k] = J($this->$k);
                        }
                    } else if (is_object($this->$k)) {
                        $db_data[$k] = J($this->$k);
                    } else {
                        $db_data[$k] = '{}';
                    }
                } elseif (array_key_exists('object_list', $v)) {
                    $db_data[$k] = isset($this->$k) ? J($this->$k->keys()) : '[]';
                } else {
                    $db_data[$k] = $this->$k;
                    if (is_null($db_data[$k]) && !array_key_exists('null', $v)) {
                        $default = $v['default'] ?? null;
                        if (array_key_exists('string', $v)) {
                            $default = is_null($default) ? '' : (string) $default;
                        } elseif (
                            array_key_exists('bool', $v)
                            || array_key_exists('int', $v)
                            || array_key_exists('bigint', $v)
                        ) {
                            $default = is_null($default) ? 0 : (int) $default;
                        } elseif (
                            array_key_exists('double', $v)
                            || array_key_exists('float', $v)
                            || array_key_exists('decimal', $v)
                        ) {
                            $default = is_null($default) ? 0.0 : (float) $default;
                        } elseif (array_key_exists('datetime', $v)) {
                            $default = is_null($default) ? '0000-00-00 00:00:00' : $default;
                        } elseif (array_key_exists('timestamp', $v)) {
                            $default = is_null($default) ? SQL('NOW()') : $default;
                        }

                        if (!is_null($default)) {
                            $db_data[$k] = $default;
                        }
                    }
                }
            }
            return $db_data;
        }

        public function dbDataCheck($db_data)
        {
            foreach ($this->structure() as $k => $v) {
                if (
                    isset($v['string']) && is_numeric($v['string'])
                    && mb_strlen($db_data[$k]) > intval($v['string'])
                ) {
                    return false;
                }
            }
            return true;
        }

        public function save()
        {
            $this->fetch();
            $db = $this->db();

            $db_data = $this->dbData();

            // diff db_data and this->_db_data
            $db_data = array_diff_assoc($db_data, (array) $this->_db_data);
            if (!$this->dbDataCheck($db_data)) {
                return false;
            }

            $db->beginTransaction();

            $tbl_name = $this->tableName();
            $id = intval($db_data['id'] ?? $this->_db_data['id'] ?? 0);
            unset($db_data['id']);

            $pairs = [];
            foreach ($db_data as $k => $v) {
                $pairs[] = $db->quoteIdent($k) . '=' .
                    (($v instanceof Database\SQL) ? strval($v) : $db->quote($v));
            }

            if (count($pairs) > 0) {
                if ($id) {
                    if (
                        $this->_db_data['id']
                        || $db->value('SELECT "id" FROM ' . $db->quoteIdent($tbl_name) . ' WHERE "id"=? FOR UPDATE', null, [$id])
                    ) {
                        // if data exists, use update to avoid unexpected overwrite.
                        $SQL = 'UPDATE ' . $db->quoteIdent($tbl_name) . ' SET ' . implode(',', $pairs) .
                            ' WHERE ' . $db->quoteIdent('id') . '=' . $db->quote($id);
                    } else {
                        array_unshift($pairs, $db->quoteIdent('id') . '=' . $db->quote($id));
                        if ($this->_forReplace) {
                            $SQL = 'REPLACE INTO ' . $db->quoteIdent($tbl_name) . ' SET ' . implode(',', $pairs);
                        } else {
                            $SQL = 'INSERT INTO ' . $db->quoteIdent($tbl_name) . ' SET ' . implode(',', $pairs);
                        }
                    }
                } else {
                    $SQL = 'INSERT INTO ' . $db->quoteIdent($tbl_name) . ' SET ' . implode(',', $pairs);
                }
            }

            if ($SQL) {
                $success = (bool) $db->query($SQL);
            } else {
                $success = true;
            }

            if ($this->_manyFields) foreach ($this->_manyFields as $k => $v) {
                if (!$v->isTouched()) continue;
                $this->saveAll($k, $v);
            }

            if ($success) {
                $id = $id ?: $db->lastInsertId();
                $this->criteria($id);
                $this->resetFetch();
            }

            if ($success) {
                $db->commit();
            } else {
                $db->rollback();
            }

            return $success;
        }

        /**
         * Inject structure declarations to current class.
         *
         * @param array|object|string $injection
         */
        public static function inject($injection)
        {
            //check all injections
            if (is_string($injection) || is_object($injection)) {
                $rc = new \ReflectionClass($injection);
                $defaults = $rc->getDefaultProperties();

                $injection = [];
                foreach ($rc->getProperties() as $p) {
                    if (!$p->isStatic() && $p->isPublic()) {
                        $k = $p->getName();
                        $injection['properties'][$k] = $defaults[$k];
                    }
                }

                $sProps = $rc->getStaticProperties();
                $sProps['db_index'] and $injection['indexes'] = $sProps['db_index'];
                $sProps['db_relation'] and $injection['relations'] = $sProps['db_relation'];
            }

            $class_name = get_called_class();
            self::$_INJECTIONS[$class_name][] = $injection;

            // clear cache
            unset(self::$_STRUCTURES[$class_name]);
            unset(self::$_INDEXES[$class_name]);
            unset(self::$_RELATIONS[$class_name]);
        }

        private static function normalizeName($str)
        {
            return preg_replace('#[-_/]+#', '_', $str);
        }

        private function _prepareName()
        {
            // remove Gini/ORM
            list(,, $name) = explode('/', str_replace('\\', '/', strtolower(get_class($this))), 3);
            $this->_name = $name;
            $this->_tableName = self::normalizeName($name);
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

        public function pivotTableName($field)
        {
            return $this->normalizeName('_' . $this->name() . '_' . strtolower($field));
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
                    $o = $data[$k] ?? null;
                    if (isset($o) && $o instanceof \Gini\ORM\Base && (!isset($oname) || $o->name() == $oname)) {
                        $this->_objects[$k] = $o;
                        $this->_oinfo[$k] = (object) ['name' => $o->name(), 'id' => $o->id];
                    } else {
                        //object need to be bind later to avoid deadlock.
                        unset($this->$k);
                        if (!isset($oname)) {
                            $oname = strval($data[$k . '_name'] ?? '');
                        }
                        if ($oname) {
                            $oi = (object) ['name' => $oname, 'id' => $data[$k . '_id'] ?? null];
                            $this->_oinfo[$k] = $oi;
                        }
                    }
                } elseif (array_key_exists('array', $v)) {
                    $this->$k = @json_decode(strval($data[$k]), true) ?: [];
                } elseif (array_key_exists('object_list', $v)) {
                    $objects = \Gini\IoC::construct('\Gini\ORMIterator', $v['object_list']);
                    $oids = (array) @json_decode(strval($data[$k]), true);
                    array_walk($oids, function ($id) use ($objects) {
                        $objects[$id] = true;
                    });
                    $this->$k = $objects;
                } elseif ($this->autocast) {
                    // 根据数据类型做类型转换
                    $types = array_keys($v);
                    if (count(array_intersect($types, ['int', 'bigint'])) > 0) {
                        $this->$k = (int) $data[$k];
                    } elseif (count(array_intersect($types, ['double', 'float', 'decimal'])) > 0) {
                        $this->$k = (float) $data[$k];
                    } elseif (count(array_intersect($types, ['bool'])) > 0) {
                        $this->$k = !!$data[$k];
                    } else {
                        $this->$k = $data[$k];
                    }
                } else {
                    $this->$k = $data[$k] ?? null;
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

            $name = \Gini\Util::hyphenate($name);

            $manyStructure = $this->manyStructure();
            if (isset($manyStructure[$name])) {
                if (!isset($this->_manyFields)) {
                    $this->_manyFields = [];
                }
                if (!isset($this->_manyFields[$name])) {
                    $this->_manyFields[$name] = $this->getAll($name);
                }
                return $this->_manyFields[$name];
            }

            if (isset($this->_objects[$name])) {
                return $this->_objects[$name];
            }

            if (isset($this->_oinfo[$name])) {
                $oi = $this->_oinfo[$name];
                $o = a($oi->name, $oi->id);
                $this->_objects[$name] = $o;
                return $o;
            }

            if (isset($this->_extra[$name])) {
                // try find it in _extra
                return $this->_extra[$name];
            }

            // 直接返回, 保证引用, 能够用于数字赋值
            if (isset($this->$name)) {
                return $this->$name;
            }

            // 以下返回值为了保证原始数据不被修改, 因此先用$ret复制后再返回

            // if $name is  {}_name or {}_id, let us get
            $parts = explode('_', $name);
            if (end($parts) === 'id') {
                array_pop($parts);
                $oname = implode('_', $parts);
                if (isset($this->_objects[$oname])) {
                    return $this->_objects[$oname]->id;
                } elseif (isset($this->_oinfo[$oname])) {
                    return $this->autocast ? intval($this->_oinfo[$oname]->id) : $this->_oinfo[$oname]->id;
                }
            }

            return $this->_db_data[$name] ?? null;
        }

        public function __set($name, $value)
        {
            // 如果之前没有触发数据库查询, 在这里触发一下
            $this->fetch();

            $name = \Gini\Util::hyphenate($name);

            $manyStructure = $this->manyStructure();
            if (isset($manyStructure[$name])) {
                if (!isset($this->_manyFields)) {
                    $this->_manyFields = [];
                }
                if (array_key_exists('object', $manyStructure[$name])) {
                    $oname = $manyStructure[$name]['object'];
                    $objects = new ORMIterator($oname);
                    if ($oname) {
                        foreach ($value as $v) {
                            $objects[$v->id] = $v;
                        }
                    } else {
                        foreach ($value as $v) {
                            $objects["{$v->name()}/{$v->id}"] = $v;
                        }
                    }

                    $this->_manyFields[$name] = $objects;
                } else {
                    $this->_manyFields[$name] = new Helper\TouchableArray($value);
                }
                return;
            }

            $structure = $this->structure();
            if (isset($structure[$name])) {
                if (array_key_exists('object', $structure[$name])) {
                    $this->_objects[$name] = $value;
                } else {
                    $this->$name = $value;
                }
                return;
            }

            // if $name is  {}_name or {}_id, let's update oinfo firstly.
            $is_object = false;
            if (preg_match('/^(.+)_id$/', $name, $idParts)) {
                $rname = $idParts[1];
                if (isset($structure[$rname]) && array_key_exists('object', $structure[$rname])) {
                    $is_object = true;
                    $oname = $structure[$rname]['object'] ?? $rname;
                    $this->_oinfo[$rname] = (object) ['name' => $oname, 'id' => (int) $value];
                    unset($this->_objects[$rname]);
                }
            }
            if ($is_object == false) {
                // 奇怪 如果之前没有强制类型转换 数组赋值会不成功
                $this->_extra = (array) $this->_extra;
                $this->_extra[$name] = $value;
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
            $name = \Gini\Util::hyphenate($name);
            // if \Gini\Config::get('system.locale') == 'zh_CN', $object->L('name') will access $object->_extra['i18n'][zh_CN]['name']
            if (!isset($locale)) {
                $locale = \Gini\Config::get('system.locale');
            }
            if (isset($this->_extra['@i18n'][$locale][$name])) {
                return $this->_extra['@i18n'][$locale][$name];
            }

            return $this->$name;
        }

        public function forUpdate($forUpdate = true)
        {
            $this->_forUpdate = !!$forUpdate;

            return $this;
        }

        /**
         * 根据ORM定义调整数据库表结构
         *
         * @return \Gini\ORM\Base
         */
        public function adjustTable()
        {
            $db = $this->db();
            if ($db) {
                $db->adjustTable($this->tableName(), $this->ormSchema());
                foreach ((array) $this->ormAdditionalSchemas() as $table => $schema) {
                    $db->adjustTable($table, $schema);
                }
            }
            return $this;
        }

        // $fields = $user->fields;
        // $friends = $user->getAll('friends');
        public function getAll($name)
        {
            $name = \Gini\Util::hyphenate($name);

            $manyStructure = $this->manyStructure();
            if (!isset($manyStructure[$name])) {
                throw new \BadMethodCallException();
            }

            $db = $this->db();
            $table = $this->pivotTableName($name);
            $objectKey = $this->normalizeName($this->name() . '_id');
            $singular = $this->normalizeName(\Gini\Util::singularize($name));
            if (array_key_exists('object', $manyStructure[$name])) {
                $oname = $manyStructure[$name]['object'];
                if ($oname) {
                    $st = $db->query('SELECT @field_id AS oid FROM @table WHERE @object_id=:id', [
                        '@table' => $table,
                        '@object_id' => $objectKey,
                        '@field_id' => $singular . '_id',
                    ], [
                        ':id' => $this->oid,
                    ]);
                    $objects = new ORMIterator($oname);
                    if ($st) while ($row = $st->row()) {
                        $objects[$row->id] = a($oname, $row->id);
                    }
                } else {
                    $st = $db->query('SELECT @field_name AS oname, @field_id AS oid FROM @table WHERE @object_id=:id', [
                        '@table' => $table,
                        '@object_id' => $objectKey,
                        '@field_name' => $singular . '_name',
                        '@field_id' => $singular . '_id',
                    ], [
                        ':id' => $this->oid,
                    ]);
                    $objects = new ORMIterator($oname);
                    if ($st) while ($row = $st->row()) {
                        $key = "{$row->oname}/{$row->id}";
                        $objects[$key] = a($row->oname, $row->id);
                    }
                }
                $values = $objects;
            } else {
                $st = $db->query('SELECT @field AS field FROM @table WHERE @object_id=:id', [
                    '@table' => $table,
                    '@object_id' => $objectKey,
                    '@field' => $singular,
                ], [
                    ':id' => $this->id,
                ]);
                if ($st) {
                    $rows = $st->rows();
                    $values = new Helper\TouchableArray(array_map(function ($v) {
                        return $v->field;
                    }, $rows));
                } else {
                    $values = new Helper\TouchableArray();
                }
            }
            return $values;
        }

        public function saveAll($name, $values)
        {
            $name = \Gini\Util::hyphenate($name);

            $manyStructure = $this->manyStructure();
            if (!isset($manyStructure[$name])) {
                throw new \BadMethodCallException();
            }

            $success = true;

            $db = $this->db();
            $table = $this->pivotTableName($name);
            $objectKey = $this->normalizeName($this->name() . '_id');
            $singular = $this->normalizeName(\Gini\Util::singularize($name));
            if (array_key_exists('object', $manyStructure[$name])) {
                if ($manyStructure[$name]['object']) {
                    $fieldId = $singular . '_id';
                    $st = $db->query('SELECT @field_id AS oid FROM @table WHERE @object_id=:object_id FOR UPDATE', [
                        '@table' => $table,
                        '@object_id' => $objectKey,
                        '@field_id' => $fieldId,
                    ], [
                        ':object_id' => $this->id,
                    ]);

                    $deletings = [];
                    $existed = [];
                    if ($st) while ($row = $st->row()) {
                        $key = $row->oid;
                        if (isset($values[$key])) {
                            $existed[$key] = $row->oid;
                        } else {
                            $deletings[$key] = $row->oid;
                        }
                    }

                    $addings = [];
                    foreach ($values as $v) {
                        $key = $v->id;
                        if (!isset($existed[$key])) {
                            $addings[] = $v->id;
                        }
                    }

                    if ($success && count($deletings) > 0) {
                        $success = $db->query('DELETE FROM @table WHERE @object_id=:object_id AND @field_id IN (:values)', [
                            '@table' => $table,
                            '@object_id' => $objectKey,
                            '@field_id' => $fieldId,
                        ], [
                            ':object_id' => $this->id,
                            ':values' => array_values($deletings),
                        ]);
                    }

                    if ($success && count($addings) > 0) {
                        $success = $db->query('INSERT INTO @table (@object_id, @field_id) VALUES :values', [
                            '@table' => $table,
                            '@object_id' => $objectKey,
                            '@field_id' => $fieldId,
                        ], [
                            ':values' => SQL(array_map(function ($id) use ($db) {
                                return '(' . $db->quote([$this->id, $id]) . ')';
                            }, $addings)),
                        ]);
                    }
                } else {
                    $fieldName = $singular . '_name';
                    $fieldId = $singular . '_id';
                    $st = $db->query('SELECT @field_name AS oname, @field_id AS oid FROM @table WHERE @object_id=:object_id FOR UPDATE', [
                        '@table' => $table,
                        '@object_id' => $objectKey,
                        '@field_name' => $fieldName,
                        '@field_id' => $fieldId,
                    ], [
                        ':object_id' => $this->id,
                    ]);

                    $deletings = [];
                    $existed = [];
                    if ($st) while ($row = $st->row()) {
                        $key = "{$row->oname}/{$row->id}";
                        if (isset($values[$key])) {
                            $existed[$key] = $row;
                        } else {
                            $deletings[$key] = $row;
                        }
                    }

                    $addings = [];
                    foreach ($values as $v) {
                        $oname = $v->name();
                        $key = "{$oname}/{$v->id}";
                        if (!isset($existed[$key])) {
                            $addings[] = [$oname, $v->id];
                        }
                    }

                    if ($success && count($deletings) > 0) {
                        $success = $db->query('DELETE FROM @table WHERE @object_id=:object_id AND (@or_where)', [
                            '@table' => $table,
                            '@object_id' => $objectKey,
                            '@or_where' => join(' OR ', array_map(function ($v) use ($db, $fieldName, $fieldId) {
                                return '('
                                    . $db->quoteIdent($fieldName) . '=' . $db->quote($v->oname)
                                    . ' AND '
                                    . $db->quoteIdent($fieldId) . '=' . $db->quote($v->oid)
                                    . ')';
                            }, $deletings))
                        ], [
                            ':object_id' => $this->id,
                        ]);
                    }

                    if ($success && count($addings) > 0) {
                        $success = $db->query('INSERT INTO @table (@object_id, @field_name, @field_id) VALUES :values', [
                            '@table' => $table,
                            '@object_id' => $objectKey,
                            '@field_name' => $fieldName,
                            '@field_id' => $fieldId,
                        ], [
                            ':values' => SQL(array_map(function ($v) use ($db) {
                                return '(' . $db->quote([$this->id, $v[0], $v[1]]) . ')';
                            }, $addings)),
                        ]);
                    }
                }
            } else {
                $st = $db->query('SELECT @field AS field FROM @table WHERE @object_id=:object_id', [
                    '@table' => $table,
                    '@object_id' => $objectKey,
                    '@field' => $singular,
                ], [
                    ':object_id' => $this->id,
                ]);

                $deletings = [];
                $existed = [];
                if ($st) while ($row = $st->row()) {
                    $key = $row->field;
                    if (isset($values[$key])) {
                        $existed[$key] = $row->field;
                    } else {
                        $deletings[$key] = $row->field;
                    }
                }

                $addings = [];
                foreach ($values as $v) {
                    if (!isset($existed[$v])) {
                        $addings[] = $v;
                    }
                }

                if ($success && count($deletings) > 0) {
                    $success = $db->query('DELETE FROM @table WHERE @object_id=:object_id AND @field IN (:values)', [
                        '@table' => $table,
                        '@object_id' => $objectKey,
                        '@field' => $singular,
                    ], [
                        ':object_id' => $this->id,
                        ':values' => array_values($deletings),
                    ]);
                }
                if ($success && count($addings) > 0) {
                    $success = $db->query('INSERT INTO @table (@object_id, @field) VALUES :values', [
                        '@table' => $table,
                        '@object_id' => $objectKey,
                        '@field' => $singular,
                    ], [
                        ':values' => SQL(array_map(function ($id) use ($db) {
                            return '(' . $db->quote([$this->id, $id]) . ')';
                        }, $addings)),
                    ]);
                }
            }

            return $success;
        }

        public function removeAll($field)
        {
            if (!$this->id) {
                return false;
            }

            $field = \Gini\Util::hyphenate($field);

            $db = $this->db();
            $success = $db->query('DELETE FROM @table WHERE @object_id=:id', [
                '@table' => $this->pivotTableName($field),
                '@object_id' => $this->normalizeName($this->name() . '_id'),
            ], [
                ':id' => $this->id,
            ]);
            return !!$success;
        }
    }
}

namespace {
    $app = \Gini\Core::app();
    if (is_subclass_of($app, '\Gini\Module\Prototype')) {
        $app->register('orm', function ($name, $criteria) {
            $class_name = '\Gini\ORM\\' . str_replace('/', '\\', $name);
            return \Gini\IoC::construct($class_name, $criteria);
        });
    }

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
            $class_name = '\Gini\ORM\\' . str_replace('/', '\\', $name);
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
}
