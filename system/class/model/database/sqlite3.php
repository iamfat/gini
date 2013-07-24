<?php

// SQLITE3 支持

namespace Model\Database {

    final class SQLite3 extends \PDO implements Driver {

        private $_table_status = null;
        private $_table_schema = null;

        private $_info;

        private $_h;

        function __construct($info){
            $this->_info = $info;
            $this->connect();
        }
        
        function quote_ident($s){
            return '"'.addslashes($s).'"';
        }
        
        private function _update_table_status($table=null) {
            if ($table || !$this->_table_status) {
                
                if ($table && $table != '*') {
                    unset($this->_table_status[$table]);
                    $SQL = sprintf('SELECT "name" FROM "sqlite_master" WHERE "type"=\'table\' AND name=%s', $this->quote($table));
                }
                else {
                    $this->_table_status = null;
                    $SQL = 'SELECT "name" FROM "sqlite_master" WHERE "type"=\'table\'';
                }

                $rs = $this->query($SQL);
                while ($r = $rs->row()) {
                    $this->_table_status[$r->name] = true;
                }
            }
        }

        function table_exists($table){
            return isset($this->_table_status[$table]);
        }

        function table_status($table) {
            $this->_update_table_status();
            return $this->_table_status[$table];
        }

        private static function _normalize_type($type) {
            // 确保小写
            $type = strtolower($type);
            // 移除多余空格
            $type = preg_replace('/\s+/', ' ', $type); 
            // 去除多级整数的长度说明
            $type = preg_replace('/\b(tinyint|smallint|mediumint|bigint|int)\s*(\(\s*\d+\s*\))*/', 'int', $type);

            return $type;
        }

        function adjust_table($table, $schema) {
            
            // $remove_nonexistent = _CONF('database.remove_nonexistent') ?: false;
            $this->_update_table_status($table);
            if (!$this->table_exists($table)) {
                $need_new_table = true;
            }

            $fields = $schema['fields'];
            $indexes = $schema['indexes'];
            $curr_schema = $this->table_schema($table);
            $curr_fields = $curr_schema['fields'];
            $curr_indexes = $curr_schema['indexes'];

            if ($curr_indexes['PRIMARY']['type'] == 'primary') {
                unset($curr_indexes['PRIMARY']);
            }
            
            if (!$need_new_table) {
                //检查所有Fields
                $missing_fields = array_diff_key($fields, $curr_fields);
                $need_new_table = (count($missing_fields) > 0);

                if (!$need_new_table) {
                    foreach ($curr_fields as $key=>$curr_field) {
                        $field = $fields[$key];
                        if ($field) {
                            $curr_type = $this->_normalize_type($curr_field['type']);
                            $type = $this->_normalize_type($field['type']);
                            if ( $type !== $curr_type
                                || $field['null'] != $curr_field['null']
                                || $field['default'] != $curr_field['default']
                                || $field['serial'] != $curr_field['serial']) {
                                $need_new_table = true;
                                break;
                            }
                        }
                    }
                }

            }

            if ($need_new_table) {
    
                foreach($curr_indexes as $key=>$curr_val) {
                    $SQL = sprintf('DROP INDEX %s', $this->quote_ident($table.'__'.$key));
                    $this->query($SQL);
                }
                $index_modified = true;

                $field_sql = [];
                $field_names = [];
                $field_values = [];

                foreach ($fields as $key=>$field) {
                    $field_sql[] = $this->field_sql($key, $field);
                    $field_names[] = $this->quote_ident($key);
                    if (isset($curr_fields[$key])) {
                        $field_values[] = $this->quote_ident($key);
                    }
                    else {
                        $field_values[] = $this->quote($field['default']);
                    }
                }

                if ($indexes['PRIMARY']['type'] == 'primary') {
                    $primary_keys = $indexes['PRIMARY']['fields'];
                    foreach($primary_keys as &$key) {
                        if ($fields[$key]['serial']) {
                            $primary_keys = null;
                            break;
                        }
                    }

                    if ($primary_keys) {
                        $field_sql[] = sprintf('PRIMARY KEY (%s)', $this->quote_ident($primary_keys));
                    }

                    unset($indexes['PRIMARY']);
                }

                // 1. 建立新表
                $SQL = sprintf('CREATE TABLE IF NOT EXISTS %s (%s)', 
                            $this->quote_ident('_new_'.$table), 
                            implode(', ', $field_sql)
                            );
                $this->query($SQL);

                // 2. 移动数据
                if ($this->table_exists($table) && count($fields) > 0) {
                    $SQL = sprintf('INSERT INTO %s (%s) SELECT %s FROM %s',
                        $this->quote_ident('_new_'.$table), 
                        implode(',', $field_names), implode(',', $field_values), $this->quote_ident($table)
                        );
                    $this->query($SQL);

                    $SQL = sprintf('DROP TABLE IF EXISTS %s', $this->quote_ident($table));
                    $this->query($SQL);
                }

                // 3. 表改名
                $SQL = sprintf('ALTER TABLE %s RENAME TO %s', $this->quote_ident('_new_'.$table), $this->quote_ident($table));
                $this->query($SQL);
            }
            else {
                foreach($curr_indexes as $key=>$curr_val) {
                    $val = & $indexes[$key];
                    if ($val) {
                        if ( $val['type'] != $curr_val['type']
                            || array_diff($val, $curr_val)) {
                            
                        }
                        else {
                            continue;
                        }
                    }

                    $SQL = sprintf('DROP INDEX IF EXISTS %s', $this->quote_ident($table.'__'.$key));
                    $this->query($SQL);

                    $index_modified = true;
                }
            }

            if ($index_modified) {
                foreach($indexes as $key=>$val) {
                    $SQL = sprintf('CREATE %sINDEX IF NOT EXISTS %s ON %s (%s)', 
                                $val['type'] ? 'UNIQUE ' : '', 
                                $this->quote_ident($table.'__'.$key), 
                                $this->quote_ident($table),
                                $this->quote_ident($val['fields'])
                            );
                    $this->query($SQL);
                }
            }
            if ($need_new_table || $index_modified) {
                $this->table_schema($table, true);
                $this->_update_table_status($table);
            }
        }

        function table_schema($name, $refresh = false) {
            
            $indexes = array();
            $fields=array();
            $primary_keys = array();

            if ($refresh || !isset($this->_table_schema[$name]['fields'])) {

                $ds = $this->query(sprintf('SELECT sql FROM sqlite_master WHERE type=\'table\' AND name=%s', $this->quote($name)));
                $table_sql = $ds->fetchObject()->sql;
                
                $ds = $this->query(sprintf('PRAGMA table_info(%s)', $this->quote_ident($name)));
                // cid, name, type, notnull, dflt_value, pk
                while($dr = $ds->fetchObject()) {

                    $field = array('type' => $this->_normalize_type($dr->type));

                    if ($dr->dflt_value !== null) {
                        switch($field['type']) {
                        case 'int':
                            $field['default'] = (int) $dr->dflt_value;
                            break;
                        case 'double':
                            $field['default'] = (float) $dr->dflt_value;
                            break;
                        default:
                            $field['default'] = (string) $dr->dflt_value;
                        }
                    }

                    if (!$dr->notnull) {
                        $field['null'] = true;
                    }                

                    if (false !== strpos($table_sql, $this->quote_ident($dr->name).' INTEGER PRIMARY KEY AUTOINCREMENT')) {
                        $field['serial'] = true;
                    }

                    $fields[$dr->name] = $field;

                    if ($dr->pk) {
                        $primary_keys[] = $dr->name;
                    }
                }

                $this->_table_schema[$name]['fields'] = $fields;
            }
            
            if ($refresh || !isset($this->_table_schema[$name]['indexes'])) {
                $ds = $this->query(sprintf('PRAGMA index_list("%s")', $this->escape($name)));

                if ($ds) while($row = $ds->fetchObject()) {
                    list($tname,$index_name) = explode('__', $row->name, 2);
                    if ($tname != $name) continue;
                    if ($row->unique) {
                        $indexes[$index_name]['type'] = 'unique';
                    }
                    $ds2 = $this->query(sprintf('PRAGMA index_info("%s")', $this->escape($row->name)));
                    if ($ds2) while ($row2 = $ds2->fetchObject()) {
                        $indexes[$index_name]['fields'][] = $row2->name;
                    }
                }

                if (count($primary_keys) > 0) {
                    $indexes['PRIMARY']['type'] = 'primary';
                    $indexes['PRIMARY']['fields'] = $primary_keys;
                }
                
                $this->_table_schema[$name]['indexes'] = $indexes;
            }

            return $this->_table_schema[$name];
        }

        private function field_sql($key, &$field) {
            if ($field['serial']) {
                return sprintf('%s INTEGER PRIMARY KEY AUTOINCREMENT'
                        , $this->quote_ident($key)
                        );
            }

            return sprintf('%s %s%s%s'
                    , $this->quote_ident($key)
                    , $this->_normalize_type($field['type'])
                    , $field['null']? '': ' NOT null'
                    , isset($field['default']) ? ' DEFAULT '.$this->quote($field['default']):''
                    );
        }
        
        function create_table($table, $engine=null) {
             
            $engine = $engine ?: 'innodb';    //innodb as default db
            
            $SQL = sprintf('CREATE TABLE IF NOT EXISTS %s ("_FOO" int NOT null)', $this->quote_ident($table));
            $rs = $this->query($SQL);
            $this->_update_table_status($table);
            
            return $rs !== null;
        
        }

        function begin_transaction() {
            @$this->query("BEGIN TRANSACTION");
        }
        
        function commit() {
            @$this->query("COMMIT TRANSACTION");
        }
        
        function rollback() {
            @$this->query("ROLLBACK TRANSACTION");
        }
        
        function drop_table($table) {
            $this->query('DROP TABLE IF EXISTS '.$this->quote_ident($table));
            $this->_update_table_status($table);
            unset($this->_prepared_tables[$table]);
            unset($this->_table_fields[$table]);
            unset($this->_table_indexes[$table]);        
        }
        
        function snapshot($filename, $tables) {
            // TODO
            // NOT IMPLEMENTED
            return false;
        }
        
        function restore($filename, &$retore_filename, $tables) {
            // TODO
            // NOT IMPLEMENTED
            return false;
        }
        
        function empty_database() {
            $rs = $this->query("SELECT name FROM sqlite_master WHERE type='table'");
            while ($r = $rs->fetch(\PDO::FETCH_NUM)) {
                if (strncmp($r[0], 'sqlite_', 7) == 0) continue;
                $tables[] = $r[0];
            }

            foreach ((array) $tables as $table) {
                $this->query('DROP TABLE IF EXISTS '.$this->quote_ident($table));
            }
        }
        
    }

}

    