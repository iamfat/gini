<?php

namespace Gini\Database {

    final class MySQL extends \PDO implements Driver {

        private $_options;

        private $_dbname;
        private $_table_status;
        private $_table_schema;

        private function _update_table_status($table=null) {
            if ($table || !$this->_table_status) {
                
                if ($table && $table != '*') {
                    unset($this->_table_status[$table]);
                    $SQL = sprintf('SHOW TABLE STATUS FROM %s WHERE "Name"=%s', 
                            $this->quoteIdent($this->_dbname), $this->quote($table));
                }
                else {
                    $this->_table_status = null;
                    $SQL = sprintf('SHOW TABLE STATUS FROM %s', 
                            $this->quoteIdent($this->_dbname));
                }

                $rs = $this->query($SQL);
                while ($r = $rs->fetchObject()) {
                    $this->_table_status[$r->Name] = (object) array(
                        'engine' => strtolower($r->Engine),
                        'collation' => $r->Collation,
                    );
                }
            }
        }

        function __construct($dsn, $username=null, $password=null, $options=null) {
            $options = (array)$options;
            $options += [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''];
            parent::__construct($dsn, $username, $password, $options);
            $this->_options = $options;

            if (preg_match('/dbname\s*=\s*(\w+)/', $dsn, $parts)) {
                $this->_dbname = $parts[1];
            }

            //enable ANSI mode
            $this->query('SET sql_mode=\'ANSI\'');
        }
        
        function quoteIdent($name) {
            if (is_array($name)) {
                $v = [];
                foreach($name as $n) {
                    $v[] = $this->quoteIdent($n);
                }
                return implode(',', $v);
            }
            return '"'.addslashes($name).'"';
        }
        
        function tableExists($table) {
            return isset($this->_table_status[$table]);
        }

        function tableStatus($table) {
            $this->_update_table_status();
            return $this->_table_status[$table];
        }

        private static function _normalize_type($type) {
            // 确保小写
            $type = strtolower($type);
            // 移除多余空格
            $type = preg_replace('/\s+/', ' ', $type); 
            // 去除多级整数的长度说明
            $type = preg_replace('/\b(tinyint|smallint|mediumint|bigint|int)\s*\(\s*\d+\s*\)/', '$1', $type);
            
            return $type;
        }

        function adjustTable($table, $schema) {
            
            // $remove_nonexistent = _CONF('database.remove_nonexistent') ?: false;
            
            if (!$this->tableExists($table)) {
                $this->createTable($table);
            }

            $field_sql = array();

            $fields = $schema['fields'];
            $indexes = $schema['indexes'];

            $curr_schema = $this->tableSchema($table);
            //检查所有Fields
            $curr_fields = $curr_schema['fields'];
            $missing_fields = array_diff_key($fields, $curr_fields);
            foreach ($missing_fields as $key=>$field) {
                $field_sql[]='ADD '.$this->field_sql($key, $field);
            }
            
            foreach ($curr_fields as $key=>$curr_field) {
                $field = $fields[$key];
                if ($field) {
                    $curr_type = $this->_normalize_type($curr_field['type']);
                    $type = $this->_normalize_type($field['type']);
                    if ( $type !== $curr_type
                        || $field['null'] != $curr_field['null']
                        || $field['default'] != $curr_field['default']
                        || $field['serial'] != $curr_field['serial']) {
                        $field_sql[] = sprintf('CHANGE %s %s'
                            , $this->quoteIdent($key)
                            , $this->field_sql($key, $field));
                    }
                }
                /*
                elseif ($remove_nonexistent) {
                    $field_sql[] = sprintf('DROP %s', $this->quoteIdent($key) );
                }
                */
                /*
                elseif ($key[0] != '@') {
                    $nkey = '@'.$key;
                    while (isset($curr_fields[$nkey])) {
                        $nkey .= '_';
                    }

                    $field_sql[] = sprintf('CHANGE %s %s'
                        , $this->quoteIdent($key)
                        , $this->field_sql($nkey, $curr_field));
                }
                */
            }

            if (count($fields) > 0 && isset($curr_fields['_FOO'])) {
                $field_sql[] = sprintf('DROP %s', $this->quoteIdent('_FOO'));
            }

            $curr_indexes = $curr_schema['indexes'];
            $missing_indexes = array_diff_key($indexes, $curr_indexes);

            foreach($missing_indexes as $key=>$val) {
                $field_sql[] = sprintf('ADD %s'
                    , $this->alter_index_sql($key, $val));
            }

            foreach($curr_indexes as $key=>$curr_val) {
                $val = & $indexes[$key];
                if ($val) {
                    if ( $val['type'] != $curr_val['type']
                        || array_diff($val, $curr_val)) {

                        $field_sql[]=sprintf('DROP %s, ADD %s'
                            , $this->alter_index_sql($key, $curr_val, true)
                            , $this->alter_index_sql($key, $val));
                    }
                }
                // remove other indexes
                else {
                    $field_sql[]=sprintf('DROP INDEX %s', $this->quoteIdent($key) );
                }
            }

            if (count($field_sql)>0) {
                $SQL = sprintf('ALTER TABLE %s %s', 
                    $this->quoteIdent($table), implode(', ', $field_sql));
                $this->query($SQL);
                $this->tableSchema($table, true);
            }

        }

        function tableSchema($name, $refresh = false) {
            
            if ($refresh || !isset($this->_table_schema[$name]['fields'])) {

                $ds = $this->query(sprintf('SHOW FIELDS FROM "%s"', $name));

                $fields=array();
                if ($ds) while($dr = $ds->fetchObject()) {

                    $field = array('type' => $this->_normalize_type($dr->Type));

                    if ($dr->Default !== null) {
                        $field['default'] = $dr->Default;
                    }

                    if ($dr->Null != 'NO') {
                        $field['null'] = true;
                    }                

                    if (false !== strpos($dr->Extra, 'auto_increment')) {
                        $field['serial'] = true;
                    }

                    $fields[$dr->Field] = $field;
                }

                $this->_table_schema[$name]['fields'] = $fields;
            }
            
            if ($refresh || !isset($this->_table_schema[$name]['indexes'])) {
                $ds = $this->query(sprintf('SHOW INDEX FROM %s', $this->quoteIdent($name)));
                $indexes = array();
                if ($ds) while($row = $ds->fetchObject()) {
                    $indexes[$row->Key_name]['fields'][] = $row->Column_name;
                    if (!$row->Non_unique) {
                        $indexes[$row->Key_name]['type'] = $row->Key_name == 'PRIMARY' ? 'primary' : 'unique';
                    }
                }
                
                $this->_table_schema[$name]['indexes'] = $indexes;
            }

            return $this->_table_schema[$name];
        }

        private function field_sql($key, &$field) {
            return sprintf('%s %s%s%s%s'
                    , $this->quoteIdent($key)
                    , $field['type']
                    , $field['null']? '': ' NOT NULL'
                    , isset($field['default']) ? ' DEFAULT '.$this->quote($field['default']):''
                    , $field['serial'] ? ' AUTO_INCREMENT':''
                    );
        }
        
        private function index_sql($key, &$val, $no_fields = false) {
            switch($val['type']){
            case 'primary':
                $type='PRIMARY KEY';
                break;
            case 'unique':
                $type='UNIQUE KEY '. $this->quoteIdent($key);
                break;
            default:
                $type='KEY '. $this->quoteIdent($key);
            }
            
            if ($no_fields) {
                return $type;
            }
            else {
                return sprintf('%s (%s)', $type, $this->quoteIdent($val['fields']));
            }
        }
        
        private function alter_index_sql($key, &$val, $no_fields = false) {
            switch($val['type']){
            case 'primary':
                $type='PRIMARY KEY';
                break;
            case 'unique':
                $type='UNIQUE '. $this->quoteIdent($key);
                break;
            default:
                $type='INDEX '. $this->quoteIdent($key);
            }
            
            if ($no_fields) {
                return $type;
            }
            else {
                return sprintf('%s (%s)', $type, $this->quoteIdent($val['fields']));
            }
        }
        
        function createTable($table) {

            if (isset($this->_options['engine'][$table])) {
                $engine = $this->_options['engine'][$table];
            }
            elseif (isset($this->_options['engine']['*'])) {
                $engine = $this->_options['engine']['*'];
            }
            else {
                $engine = 'innodb';  //innodb as default db
            }
             
            $SQL = sprintf('CREATE TABLE IF NOT EXISTS %s (%s INT NOT NULL) ENGINE = %s DEFAULT CHARSET = utf8', 
                        $this->quoteIdent($table), 
                        $this->quoteIdent('_FOO'), 
                        $this->quote($engine)
                        );
            $rs = $this->query($SQL);
            $this->_update_table_status($table);
            
            return $rs !== null;
        
        }

        function dropTable($table) {
            $this->query('DROP TABLE '.$this->quoteIdent($table));
            $this->_update_table_status($table);
            unset($this->_prepared_tables[$table]);
            unset($this->_table_fields[$table]);
            unset($this->_table_indexes[$table]);        
        }
        
        function snapshot($filename, $tables) {
            
            $tables = (array)$tables;
            foreach ($tables as &$table) {
                $table = escapeshellarg($table);
            }
        
            $table_str = implode(' ', $tables);
        
            $dump_command = sprintf('/usr/bin/mysqldump -h %s -u %s %s %s %s > %s', 
                    escapeshellarg($this->_info['host']),
                    escapeshellarg($this->_info['user']),
                    $this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
                    escapeshellarg($this->_info['db']),
                    $table_str,
                    escapeshellarg($filename)
                    );    
            exec($dump_command, $output=null, $ret);
            return $ret == 0;
        }
        
        function emptyDatabase() {
            $rs = $this->query('SHOW TABLES');
            while ($r = $rs->fetch(\PDO::FETCH_NUM)) {
                $tables[] = $r[0];
            }
            $this->query('DROP TABLE '.$this->quoteIdent($tables));
        }
        
        function restore($filename, &$retore_filename, $tables) {
            
            $import_command = sprintf('/usr/bin/mysql -h %s -u %s %s %s < %s', 
                    escapeshellarg($this->_info['host']),
                    escapeshellarg($this->_info['user']),
                    $this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
                    escapeshellarg($this->_info['db']),
                    escapeshellarg($filename)
                    );    
            exec($import_command, $output=null, $ret);
            
            return $ret == 0;
        }
        
    }

}

    