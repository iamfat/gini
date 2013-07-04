<?php

namespace Model {

    class ORM_Iterator implements \Iterator, \ArrayAccess, \Countable {

        protected $db;

        protected $name;
        
        protected $current_id;
        protected $objects = array();
        
        protected $total_count;    //符合selector的数据总数
        protected $count;    //实际获得数据数
        
        protected $SQL;
        protected $count_SQL;

        function total_count() {
            $this->fetch('count');
            return (int) $this->total_count;
        }

        function name(){return $this->name;}

        function __construct($name){
            // 查询一下看看是不是复数
            $name = _CONF('orm.plurals')[$name] ?: $name;
            $this->name = $name;
            $db_method = '\\ORM\\'.ucwords($name).'::db';
            $this->db = call_user_func($db_method);
        }

        function __clone() {}

        function query() {
            $db = $this->db;
            $args = func_get_args();
            $this->SQL = $SQL = call_user_func_array(array($db, 'rewrite'), $args);

            $count_SQL = preg_replace('/\bSQL_CALC_FOUND_ROWS\b/', '', $SQL);
            $count_SQL = preg_replace('/^(SELECT)\s(.+?)\s(FROM)\s/', '$1 COUNT($2) AS `count` $3', $count_SQL);
            $count_SQL = preg_replace('/\bCOUNT\((.+?)\.\*\)\b/', 'COUNT($1.`id`)', $count_SQL);
            $count_SQL = preg_replace('/\sORDER BY.+$/', '', $count_SQL);
            $count_SQL = preg_replace('/\sLIMIT.+$/', '', $count_SQL);

            $this->count_SQL = $count_SQL;

            return $this;
        }

        private $_fetch_flag;
        protected function set_fetch_flag($scope, $enable=true) {
            if ($enable) {
                $this->_fetch_flag[$scope] = true;
            }
            else {
                if ($scope === '*') {
                    unset($this->_fetch_flag);
                }
                else {
                    unset($this->_fetch_flag[$scope]);
                }
            }
        }

        protected function is_fetch_flagged($scope) {
            return isset($this->_fetch_flag['*']) || isset($this->_fetch_flag[$scope]);
        }

        protected function reset_fetch() {
            if ($this->SQL) {
                $this->set_fetch_flag('*', false);
                $this->SQL = null;
                $this->count_SQL = null;
            }
        }

        protected function fetch($scope='data') {
            if ($this->is_fetch_flagged($scope)) return $this;

            switch($scope) {
            case 'count':
                $this->total_count = $this->count_SQL ? $this->db->value($this->count_SQL) : 0;
                break;
            default:
                if ($this->SQL) {
                    $result = $this->db->query($this->SQL);

                    $objects = array();

                    if ($result) {
                        while ($row = $result->row('assoc')) {
                            $objects[$row['id']] = true;
                        }
                    }

                    $this->objects = $objects;
                    $this->count = count($objects);
                    $this->current_id = key($objects);
                }
            }

            $this->set_fetch_flag($scope, true);

            return $this;
        }

        function delete_all() {
            $this->fetch();
            foreach ($this->objects as $object) {
                if (!$object->delete()) return false;
            }
            return true;
        }

        function object($id) {
            if ($this->objects[$id] === true) {
                $class_name = '\\ORM\\'.ucwords($this->name);
                $this->objects[$id] = new $class_name($id);
            }
            return $this->objects[$id];
        }

        // Iterator Start
        function rewind(){
            $this->fetch();
            reset($this->objects);
            $this->current_id = key($this->objects);
        }
        
        function current(){ 
            $this->fetch();
            return $this->object($this->current_id); 
        }
        
        function key(){
            $this->fetch();
            return $this->current_id;
        }
        
        function next(){
            $this->fetch();
            next($this->objects);
            $this->current_id = key($this->objects);
            return $this->object($this->current_id);
        }
        
        function valid(){
            $this->fetch();
            return isset($this->objects[$this->current_id]);
        }
        // Iterator End

        // Countable Start
        function count(){
            $this->fetch();
            return (int) $this->count;
        }
        // Countable End
        
        // ArrayAccess Start
        function offsetGet($id){
            $this->fetch();
            if($this->count > 0){
                return $this->object($id);
            }
            return null;
        }
        
        function offsetUnset($id){
            $this->fetch();
            unset($this->objects[$id]);
            $this->count = count($this->objects);
            if ($this->current_id==$id) $this->current_id = key($this->objects);
        }
        
        function offsetSet($id, $object){
            $this->fetch();
            $this->objects[$object->id] = $object;
            $this->count = count($this->objects);
            $this->current_id=$id;
        }
        
        function offsetExists($id){
            $this->fetch();
            return isset($this->objects[$id]);
        }

        // ArrayAccess End
        function prepend($object){
            $this->fetch();

            if ($object->id) {
                $this->objects = array($object->id => $object) + $this->objects;
            }
            
            $this->count = count($this->objects);
            return $this;
        }

        function reverse() {
            $this->fetch();
            //反排
            $this->objects = array_reverse($this->objects);
            return $this;
        }

        function get($key = 'id', $val = null) {
            $this->fetch();

            $arr = array();
            if ($val === null) {
                foreach(array_keys($this->objects) as $k) {
                    $o = $this->object($k);
                    $arr[] = $o->$key;
                }
            }
            else {
                foreach(array_keys($this->objects) as $k) {
                    $o = $this->object($k);
                    $arr[$o->$key] = $o->$val;
                }
            }
            
            return $arr;
        }

    }

}
