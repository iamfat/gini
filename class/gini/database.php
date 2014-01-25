<?php

namespace Gini\Database {

    class Exception extends \ErrorException {};
    
    interface Driver {
        function quoteIdent($s);

        function tableExists($table);
        function tableStatus($table);
        function tableSchema($name, $refresh);

        function adjustTable($table, $schema);

        function snapshot($filename, $tbls);
        function emptyDatabase();

        function createTable($table);
        function dropTable($table);

        function restore($filename, &$restore_filename, $tables);
    }

    class Statement {

        private $_pdo_st;
        
        function __construct($pdo_st){
            $this->_pdo_st = $pdo_st;
        }

        function row($style=\PDO::FETCH_OBJ) {
            return $this->_pdo_st->fetch($style);
        }
        
        function rows($style=\PDO::FETCH_OBJ) {
            return $this->_pdo_st->fetchAll($style);
        }
        
        function count(){
            return $this->_pdo_st->rowCount();
        }

        function value(){
            $r = $this->row(\PDO::FETCH_NUM);
            if (!$r) return null;
            return $r[0];
        }
        
    }

}

namespace Gini {
    
    class Database {
    
        public static $DB = array();
    
        private $_driver;
    
        static function db($name=null) {
        
            $name = $name ?: 'default';
            if(!isset(self::$DB[$name])){

                $opt = _CONF('database.'.$name);
                if (is_string($opt)) {
                    // 是一个别名
                    $db = self::db($opt);
                }
                else {
                    if (!is_array($opt)) {
                        throw new Database\Exception('database "' . $name . '" was not configured correctly!');
                    }

                    $db = new Database($opt['dsn'], $opt['username'], $opt['password'], $opt['options']);
                }
                
                self::$DB[$name] = $db;
            }
        
            return self::$DB[$name];
        }    
        
        static function shutdown($name=null) {
            $name = $name ?: 'default';
            if(!isset(self::$DB[$name])){
                unset(self::$DB[$name]);
            }
        }
    
        static function reset() {
            self::$DB = array();
        }
        
        function __construct($dsn, $username=null, $password=null, $options=null) {
            list($driver_name,) = explode(':', $dsn, 2); 
            $driver_class = '\\Gini\\Database\\'.$driver_name;
            $this->_driver = new $driver_class($dsn, $username, $password, $options);
            if (!$this->_driver instanceof Database\Driver) {
                throw new Database\Exception('unknown database driver: '.$driver_name);
            }
        }
    
        function ident() {
            $args = func_get_args();
            $ident = array();
            foreach($args as $arg) {
                $ident[] = $this->quoteIdent($arg);
            }
            return implode('.', $ident);
        }

        function quoteIdent($s){
            if(is_array($s)){
                foreach($s as &$i){
                    $i = $this->quoteIdent($i);
                }
                return implode(',', $s);
            }           
            return $this->_driver->quoteIdent($s);
        }
        
        function quote($s) {
            if(is_array($s)){
                foreach($s as &$i){
                    $i=$this->quote($i);
                }            
                return implode(',', $s);
            }
            elseif (is_null($s)) {
                return 'NULL';
            }
            elseif (is_bool($s)) {
                return $s ? 1 : 0;
            }
            elseif (is_int($s) || is_float($s)) {
                return $s;
            }
            return $this->_driver->quote($s);
        }

        function attr(int $attr) {
            return $this->_driver->getAttribute($attr);
        }
            
        function lastInsertId(string $name = null) {
            return $this->_driver->lastInsertId($name);
        }

        function query() {
            $args = func_get_args();
            if (count($args) > 1) {
                // quote all identifiers
                if (is_array($args[1])) {
                    $idents = [];
                    foreach ($args[1] as $k => $v) {
                        $idents[$k] = $this->_driver->quoteIdent($v);
                    }
                
                    $SQL = strtr($args[0], $idents);
                }
                else {
                    $SQL = $args[0];
                }
 
                if (is_array($args[2])) {
                    \Gini\Logger::of('core')->debug("Database query prepare = {SQL}", ['SQL'=>preg_replace('/\s+/', ' ', $SQL)]);
                    $st = $this->_driver->prepare($SQL);
                    if (!$st) return false;
                
                    \Gini\Logger::of('core')->debug("Database query execute = {params}", ['params'=>json_encode($args[2])]);
                    $success = $st->execute($args[2]); 
                    if (!$success) return false;
                    
                    return new Database\Statement($st);
                }
            }
            else {
                $SQL = $args[0];
            }

            \Gini\Logger::of('core')->debug("Database query = {SQL}", ['SQL'=>preg_replace('/\s+/', ' ', $SQL)]);
            $st = $this->_driver->query($SQL);
            if (!$st) return false;
            return new Database\Statement($st);
        }
    
        function value() {
            $args = func_get_args();
            $result = call_user_func_array([$this,'query'], $args);
            return $result ? $result->value() : null;
        }
        
        function beginTransaction() {
            $this->_driver->beginTransaction();
            return $this;
        }
        
        function commit() {
            $this->_driver->commit();
            return $this;
        }
        
        function rollback() {
            $this->_driver->rollBack();
            return $this;
        }
        
        function snapshot($filename, $tables = null) {
    
            if (is_string($tables)) $tables = array($tables);
            else $tables = (array)$tables;
            
            return $this->_driver->snapshot($filename, $tables);
        }
        
        function adjustTable($table, $schema) {
            return $this->_driver->adjustTable($table, $schema);
        }
        
        function createTable($table) {
            return $this->_driver->createTable($table);
        }
    
        function createTables(array $tables) {
            foreach($tables as $table) {
                $this->createTable($table);
            }
        }
        
        function dropTable($table) {
            $this->_driver->dropTable($table);
        }
        
        function dropTables(array $tables) {
            foreach($tables as $table) {
                $this->dropTable($table);
            }
        }
    
        function restore($filename, &$retore_filename=null, $tables=null) {
            $retore_filename = $filename.'.restore'.uniqid();
            if (!$this->snapshot($retore_filename)) return false;
            
            if (is_string($tables)) $tables = array($tables);
            else $tables = (array) $tables;
    
            if (count($tables) > 0) {
                call_user_func_array(array($this, 'dropTable'), $tables);
            }
            else {
                $this->emptyDatabase();
            }
            
            return $this->_driver->restore($filename, $tables);
        }
        
        function emptyDatabase() {
            return $this->_driver->emptyDatabase();
        }
    }

    
}


