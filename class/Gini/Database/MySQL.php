<?php

namespace Gini\Database;

class MySQL extends \PDO implements Driver
{
    private $_options;

    private $_dbname;
    private $_table_status;
    private $_table_schema;

    private function _updateTableStatus($table = null)
    {
        if ($table || !$this->_table_status) {
            if ($table && $table != '*') {
                unset($this->_table_status[$table]);
                $SQL = "SHOW TABLE STATUS FROM {$this->quoteIdent($this->_dbname)} WHERE \"Name\"={$this->quote($table)}";
            } else {
                $this->_table_status = null;
                $SQL = "SHOW TABLE STATUS FROM {$this->quoteIdent($this->_dbname)}";
            }
            $rs = $this->query($SQL);
            while ($r = $rs->fetchObject()) {
                $this->_table_status[$r->Name] = (object) [
                    'engine' => strtolower($r->Engine),
                    'collation' => $r->Collation,
                ];
            }
        }
    }

    public function __construct($dsn, $username = null, $password = null, $options = null)
    {
        $options = (array) $options;
        if (!isset($options['charset'])) {
            $options['charset'] = 'utf8';
        }

        parent::__construct($dsn, $username, $password, $options);
        $this->_options = $options;

        if (preg_match('/dbname\s*=\s*(\w+)/', $dsn, $parts)) {
            $this->_dbname = $parts[1];
        }

        //enable ANSI mode
        $this->query('SET sql_mode=\'ANSI\'');
    }

    public function quoteIdent($name)
    {
        if (is_array($name)) {
            $v = [];
            foreach ($name as $n) {
                $v[] = $this->quoteIdent($n);
            }

            return implode(',', $v);
        }

        return '"' . addslashes($name) . '"';
    }

    public function tableExists($table)
    {
        return isset($this->_table_status[$table]);
    }

    public function tableStatus($table)
    {
        $this->_updateTableStatus();

        return $this->_table_status[$table];
    }

    private static function _normalizeType($type)
    {
        // 确保小写
        $type = strtolower($type);
        // 移除多余空格
        $type = preg_replace('/\s+/', ' ', $type);
        // 去除多级整数的长度说明
        $type = preg_replace('/\b(tinyint|smallint|mediumint|bigint|int)\s*\(\s*\d+\s*\)/', '$1', $type);

        return $type;
    }

    public function adjustTable($table, $schema, $flag = 0)
    {
        // $remove_nonexistent = \Gini\Config::get('database.remove_nonexistent') ?: false;

        if (!$this->tableExists($table)) {
            $this->createTable($table);
        }

        $alter_sqls = [
            'drop_indexes' => [],
            'modify_fields' => [],
            'modify_data' => [],
            'add_indexes' => [],
        ];

        $fields = (array) $schema['fields'];

        $curr_schema = $this->tableSchema($table);
        //检查所有Fields
        $curr_fields = (array) $curr_schema['fields'];
        $missing_fields = array_diff_key($fields, $curr_fields);
        foreach ($missing_fields as $key => $field) {
            $alter_sqls['modify_fields'][] = "ADD {$this->_fieldSQL($key,$field)}";
        }

        foreach ($curr_fields as $key => $curr_field) {
            $field = $fields[$key];
            if ($field) {
                $curr_type = $this->_normalizeType($curr_field['type']);
                $type = $this->_normalizeType($field['type']);
                if (
                    $type !== $curr_type
                    || $field['null'] != $curr_field['null']
                    || $field['default'] != $curr_field['default']
                    || $field['serial'] != $curr_field['serial']
                ) {
                    $alter_sqls['modify_fields'][] = "CHANGE {$this->quoteIdent($key)} {$this->_fieldSQL($key,$field)}";
                    // echo "Current Fields:\n".yaml_emit($curr_field)."\n";
                    // echo "Expected Fields:\n".yaml_emit($field)."\n";
                }
            } elseif ($flag & \Gini\Database::ADJFLAG_REMOVE_NONEXISTENT) {
                $alter_sqls['modify_fields'][] = "DROP {$this->quoteIdent($key)}";
            }
            /*
            elseif ($key[0] != '@') {
                $nkey = '@'.$key;
                while (isset($curr_fields[$nkey])) {
                    $nkey .= '_';
                }
                $alter_sqls['modify_fields'][] = sprintf('CHANGE %s %s'
                    , $this->quoteIdent($key)
                    , $this->_fieldSQL($nkey, $curr_field));
            }
            */
        }

        if (count($fields) > 0 && isset($curr_fields['_FOO'])) {
            $alter_sqls['modify_fields'][] = "DROP {$this->quoteIdent('_FOO')}";
        }

        // ------ CHECK INDEXES
        $indexes = (array) $schema['indexes'];
        $curr_indexes = (array) $curr_schema['indexes'];
        $missing_indexes = array_diff_key($indexes, $curr_indexes);

        foreach ($missing_indexes as $key => $val) {
            $alter_sqls['add_indexes'][] = "ADD {$this->_addIndexSQL($key,$val)}";
        }

        foreach ($curr_indexes as $key => $curr_val) {
            $val = $indexes[$key];
            if ($val) {
                ksort($val);
                ksort($curr_val);
                if ($val != $curr_val) {
                    $alter_sqls['drop_indexes'][] = "DROP {$this->_dropIndexSQL($key,$curr_val)}";
                    $alter_sqls['add_indexes'][] = "ADD {$this->_addIndexSQL($key,$val)}";
                }
            } else {
                // remove other indexes
                $alter_sqls['drop_indexes'][] = "DROP INDEX {$this->quoteIdent($key)}";
            }
        }

        // ------ CHECK RELATIONS
        $relations = (array) $schema['relations'];

        $curr_relations = (array) $curr_schema['relations'];
        $missing_relations = array_diff_key($relations, $curr_relations);

        foreach ($missing_relations as $key => $val) {
            $alter_sqls['add_indexes'][] = "ADD {$this->_addRelationSQL($key,$val)}";
            $alter_sqls['modify_data'][] = $this->_cleanUpInvalidDataSQL($table, $val);
        }

        foreach ($curr_relations as $key => $curr_val) {
            $val = $relations[$key];
            if ($val) {
                if (array_diff($val, $curr_val)) {
                    $alter_sqls['drop_indexes'][] = "DROP FOREIGN KEY {$this->quoteIdent($key)}";
                    $alter_sqls['add_indexes'][] = "ADD {$this->_addRelationSQL($key,$val)}";
                    $alter_sqls['modify_data'][] = $this->_cleanUpInvalidDataSQL($table, $val);
                }
            } else {
                // remove other relations
                $alter_sqls['drop_indexes'][] = "DROP FOREIGN KEY {$this->quoteIdent($key)}";
            }
        }

        $error = null;
        foreach ($alter_sqls as $key => $sqls) {
            if (count($sqls) == 0) {
                continue;
            }
            if ($key === 'modify_data') {
                foreach ($sqls as $sql) {
                    try {
                        if (!$this->query($sql)) {
                            $error =  "{$this->errorInfo()[2]} SQL: $sql";
                            break;
                        }
                    } catch (\PDOException $e) {
                        $error = "{$e->getMessage()} SQL: $sql";
                    }
                }
            } else {
                $sql = 'ALTER TABLE ' . $this->quoteIdent($table) . ' ' .  implode(', ', $sqls);
                try {
                    if (!$this->query($sql)) {
                        $error =  "{$this->errorInfo()[2]} SQL: $sql";
                    }
                } catch (\PDOException $e) {
                    $error = "{$e->getMessage()} SQL: $sql";
                }
            }
            if ($error) break;
        }
        if ($error) {
            throw new \Gini\Database\Exception($error);
        }

        $this->tableSchema($table, true);
    }

    public function tableSchema($name, $refresh = false)
    {
        if ($refresh || !isset($this->_table_schema[$name]['fields'])) {
            $ds = $this->query("SHOW FIELDS FROM {$this->quoteIdent($name)}");

            $fields = [];
            if ($ds) {
                while ($dr = $ds->fetch(\PDO::FETCH_ASSOC)) {
                    $field = ['type' => $this->_normalizeType($dr['Type'])];

                    if ($dr['Default'] !== null) {
                        $field['default'] = $dr['Default'];
                    }

                    if ($dr['Null'] != 'NO') {
                        $field['null'] = true;
                    }

                    if (false !== strpos($dr['Extra'], 'auto_increment')) {
                        $field['serial'] = true;
                    }

                    $fields[$dr['Field']] = $field;
                }
            }

            $this->_table_schema[$name]['fields'] = $fields;
        }

        if ($refresh || !isset($this->_table_schema[$name]['indexes'])) {
            $ds = $this->query('SHOW INDEX FROM ' . $this->quoteIdent($name));
            $indexes = [];
            if ($ds) {
                while ($row = $ds->fetchObject()) {
                    $indexes[$row->Key_name]['fields'][] = $row->Column_name;
                    if (!$row->Non_unique) {
                        $indexes[$row->Key_name]['type'] = $row->Key_name == 'PRIMARY' ? 'primary' : 'unique';
                    } elseif ($row->Index_type == 'FULLTEXT') {
                        $indexes[$row->Key_name]['type'] = 'fulltext';
                    }
                }
            }
            $this->_table_schema[$name]['indexes'] = $indexes;
        }

        if ($refresh || !isset($this->_table_schema[$name]['relations'])) {
            $ds = $this->query("SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = {$this->quote($this->_dbname)} AND TABLE_NAME = {$this->quote($name)} AND REFERENCED_TABLE_NAME IS NOT NULL");
            $relations = [];
            if ($ds) {
                while ($row = $ds->fetchObject()) {
                    $relations[$row->CONSTRAINT_NAME] = [
                        'table' => $name,
                        'column' => $row->COLUMN_NAME,
                        'ref_table' => $row->REFERENCED_TABLE_NAME,
                        'ref_column' => $row->REFERENCED_COLUMN_NAME,
                    ];
                    unset($this->_table_schema[$name]['indexes'][$row->CONSTRAINT_NAME]);
                }
            }
            $this->_table_schema[$name]['relations'] = $relations;
        }

        return $this->_table_schema[$name];
    }

    private function _fieldSQL($key, $field)
    {
        if (isset($field['default'])) {
            if (
                in_array($field['type'], ['datetime', 'timestamp'])
                && $field['default'] == 'CURRENT_TIMESTAMP'
            ) {
                $default = $field['default'];
            } elseif (!in_array($field['type'], ['text', 'mediumtext', 'longtext'])) {
                $default = $field['serial'] ? null : $this->quote($field['default']);
            }
        }

        return $this->quoteIdent($key) . ' ' .
            $field['type'] .
            ($field['null'] ? '' : ' NOT NULL') .
            ($default ? ' DEFAULT ' . $default : '') .
            ($field['serial'] ? ' AUTO_INCREMENT PRIMARY KEY' : '');
    }

    private function _dropIndexSQL($key, $val)
    {
        switch ($val['type']) {
            case 'primary':
                $type = 'PRIMARY KEY';
                break;
            default:
                $type = "INDEX {$this->quoteIdent($key)}";
        }

        return $type;
    }

    private function _addIndexSQL($key, $val)
    {
        $quotedKey = $this->quoteIdent($key);
        switch ($val['type']) {
            case 'primary':
                $type = 'PRIMARY KEY';
                break;
            case 'unique':
                $type = 'UNIQUE ' . $quotedKey;
                break;
            case 'fulltext':
                $type = 'FULLTEXT ' . $quotedKey;
                break;
            default:
                $type = 'INDEX ' . $quotedKey;
        }

        $quotedFields = $this->quoteIdent($val['fields']);
        return "$type ($quotedFields)";
    }

    private function _addRelationSQL($key, $val)
    {
        switch ($val['delete']) {
            case 'restrict':
                $deleteAction = 'RESTRICT';
                break;
            case 'cascade':
                $deleteAction = 'CASCADE';
                break;
            case 'null':
                $deleteAction = 'SET NULL';
                break;
            default:
                $deleteAction = 'NO ACTION';
        }

        switch ($val['update']) {
            case 'restrict':
                $updateAction = 'RESTRICT';
                break;
            case 'cascade':
                $updateAction = 'CASCADE';
                break;
            case 'null':
                $updateAction = 'SET NULL';
                break;
            default:
                $updateAction = 'NO ACTION';
        }

        return "CONSTRAINT {$this->quoteIdent($key)} FOREIGN KEY ({$this->quoteIdent($val['column'])}) REFERENCES {$this->quoteIdent($val['ref_table'])} ({$this->quoteIdent($val['ref_column'])}) ON DELETE $deleteAction ON UPDATE $updateAction";
    }

    private function _cleanUpInvalidDataSQL($table, $val)
    {
        // 修正破坏完整性的数据
        $quotedTable = $this->quoteIdent($table);
        $quotedColumn = $this->quoteIdent($val['column']);
        $quotedRefTable = $this->quoteIdent($val['ref_table']);
        $quotedRefColumn = $this->quoteIdent($val['ref_column']);
        if ($val['delete'] == 'null') {
            return "UPDATE IGNORE $quotedTable SET $quotedColumn=NULL WHERE NOT EXISTS (SELECT * FROM $quotedRefTable WHERE $quotedRefColumn=$quotedTable.$quotedColumn)";
        } else {
            return "DELETE IGNORE FROM $quotedTable WHERE NOT EXISTS (SELECT * FROM $quotedRefTable WHERE $quotedRefColumn=$quotedTable.$quotedColumn)";
        }
    }

    public function createTable($table)
    {
        if (isset($this->_options['engine'][$table])) {
            $engine = $this->_options['engine'][$table];
        } elseif (isset($this->_options['engine']['*'])) {
            $engine = $this->_options['engine']['*'];
        } else {
            $engine = 'innodb';  //innodb as default db
        }

        $SQL = "CREATE TABLE IF NOT EXISTS {$this->quoteIdent($table)} ({$this->quoteIdent('_FOO')} INT NOT NULL) ENGINE = {$this->quote($engine)} DEFAULT CHARSET = utf8";
        $rs = $this->query($SQL);
        $this->_updateTableStatus($table);

        return $rs !== null;
    }

    public function dropTable($table)
    {
        $this->query("DROP TABLE {$this->quoteIdent($table)}");
        unset($this->_table_status[$table]);
        unset($this->_table_schema[$table]);
        return true;
    }

    public function emptyDatabase()
    {
        $rs = $this->query('SHOW TABLES');
        while ($r = $rs->fetch(\PDO::FETCH_NUM)) {
            $tables[] = $r[0];
        }
        $this->query("TRUNCATE TABLE IF EXISTS {$this->quoteIdent($tables)}");
        return true;
    }

    //COMMENTED FOR STABILITY REASON
    // function snapshot($filename, $tables) {
    //
    //     $tables = (array) $tables;
    //     foreach ($tables as &$table) {
    //         $table = escapeshellarg($table);
    //     }
    //
    //     $table_str = implode(' ', $tables);
    //
    //     $dump_command = sprintf('/usr/bin/mysqldump -h %s -u %s %s %s %s > %s',
    //             escapeshellarg($this->_info['host']),
    //             escapeshellarg($this->_info['user']),
    //             $this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
    //             escapeshellarg($this->_info['db']),
    //             $table_str,
    //             escapeshellarg($filename)
    //             );
    //     exec($dump_command, $output=null, $ret);
    //     return $ret == 0;
    // }
    //
    // function restore($filename, &$retore_filename, $tables) {
    //
    //     $import_command = sprintf('/usr/bin/mysql -h %s -u %s %s %s < %s',
    //             escapeshellarg($this->_info['host']),
    //             escapeshellarg($this->_info['user']),
    //             $this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
    //             escapeshellarg($this->_info['db']),
    //             escapeshellarg($filename)
    //             );
    //     exec($import_command, $output=null, $ret);
    //
    //     return $ret == 0;
    // }

    public function diagnose()
    {
        //
        $engines = [];
        if (!empty($this->_options['engine'])) {
            foreach ((array) $this->_options['engine'] as $k => $v) {
                array_push($engines, strtolower($v));
            }
        } else {
            array_push($engines, 'innodb');
        }
        $engines = array_unique($engines);

        $supportedEngines = [];
        foreach ((new Statement($this->query('SHOW ENGINES')))->rows() as $obj) {
            $engine = strtolower($obj->Engine);
            array_push($supportedEngines, $engine);
        }

        $diff = array_diff($engines, $supportedEngines);
        if (!empty($diff)) {
            return ['MySQL does not support following engines: ' . implode(',', $diff)];
        }
    }
}
