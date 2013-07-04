<?php

//TODO: Drizzle目前还不支持呢...

namespace Model\Database;

use \Model\Config;

final class Drizzle implements \Model\Database\Driver {

    private $_info;
    private $_h;

    function __construct($info){
        $this->_info = $info;
        $this->connect();
    }
    
    function connect() {

    }
    
    function is_connected() {

    }

    function escape($s) {
        // return $this->_h ? $this->_h->escape_string($s) : addslashes($s);
    }

    function quote_ident($s){
        if (is_array($s)) {
            foreach($s as &$i){
                $i = $this->quote_ident($i);
            }
            return implode(',', $s);
        }        
        return '`'.$this->escape($s).'`';
    }
    
    function quote($s) {
        if(is_array($s)){
            foreach($s as &$i){
                $i=$this->quote($i);
            }            
            return implode(',', $s);
        }
        elseif (is_bool($s) || is_int($s) || is_float($s)) {
            return $s;
        }
        return '\''.$this->escape($s).'\'';
    }

    function query($SQL) {
        $retried = 0;

        while (1) {
            $result = @$this->_h->query($SQL);
            if (is_object($result)) return new DBResult($result);

            if ($this->_h->errno != 2006) break;
            if ($retried > 0) {
                trigger_error('database gone away!');
            }

            $this->connect();
            $retried ++;
        }

        return $result;
    }

    function insert_id() {

    }

    function affected_rows() {

    }

    function table_exists($table){
        return false;
    }

    function table_status($table) {
        return array('engine'=>'unknown', 'collation'=>'utf8');
    }

    function adjust_table($table, $schema) {
        
    }

    function table_schema($name, $refresh = false) {
        
        return array();
    }

    function create_table($table, $engine=null) {
         
        $engine = $engine ?: 'innodb';    //innodb as default db
        
        $SQL = $this->rewrite('CREATE TABLE `%s` (`%s` int NOT null) ENGINE = %s DEFAULT CHARSET = utf8', $table, '_FOO', $engine);
        $rs = $this->query($SQL);
        $this->_update_table_status($table);
        
        return $rs !== null;
    
    }

    function begin_transaction() {
        @$this->_h->autocommit(false);
    }
    
    function commit() {
        @$this->_h->commit();
        @$this->_h->autocommit(true);
    }
    
    function rollback() {
        @$this->_h->rollback();
        @$this->_h->autocommit(true);
    }
    
    function drop_table($table) {
        $this->query('DROP TABLE '.$this->quote_ident($table));
        $this->_update_table_status($table);
        unset($this->_prepared_tables[$table]);
        unset($this->_table_fields[$table]);
        unset($this->_table_indexes[$table]);        
    }
    
    function snapshot($filename, $tables) {
        return false;        
    }
    
    function empty_database() {

    }
    
    function restore($filename, &$retore_filename, $tables) {
        
        return false;
    }

    function fetch_row($result, $mode='object') {
        return array();        
    }

    function num_rows($result) {
        return 0;
    }
    
}
