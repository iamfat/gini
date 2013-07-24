<?php

namespace Test\Unit\System\Database {

    class SQLite3 extends \Model\Test\Unit {

        var $db;

        function setup() {
            _CONF('database.gini_ut.url', 'sqlite3://gini_ut.sqlite');    
            $this->db = \Model\Database::db('gini_ut');
        }

        function test_config() {
            $db = $this->db;

            $url = $this->get_property($db, '_url');
            $this->assert('db[url]', $url == 'sqlite3://gini_ut.sqlite');

        }

        function test_create_table() {
            $db = $this->db;

            $db->create_tables(['test1', 'test2:innodb', 'test3']);

            $this->assert('table_exists(test1)', $db->table_exists('test1'));
            $this->assert('table_exists(test2)', $db->table_exists('test2'));
            $this->assert('table_exists(test3)', $db->table_exists('test3'));
        }

        function test_adjust_table() {
            $this->depend('create_table');

            $db = $this->db;

            $schema1 = array (
                'fields' => array (
                    'name' => array ( 'type' => 'varchar(60)' ),
                    'gender' => array ( 'type' => 'int'),
                    'money' => array ( 'type' => 'double', 'default' => 0.0),
                    'description' => array ('type' => 'text', 'null' => true),
                    'id' => array ( 'type' => 'int' ),
                ),
                'indexes' => array (
                    'PRIMARY' => array ( 'type' => 'primary', 'fields' => array('id') ),
                    '_name' => array ( 'type' => 'unique', 'fields' => array('name')),
                ),
            );
            $db->adjust_table('test1', $schema1);

            $curr_schema = $db->table_schema('test1');
            $diff1 = $this->diff_array_deep($schema1, $curr_schema);
            $this->assert('check test1 schema', count($diff1) == 0);

            $schema2 = array (
                'fields' => array (
                    'name' => array ( 'type' => 'varchar(20)' ),
                    'gender' => array ( 'type' => 'int'),
                    'money' => array ( 'type' => 'double', 'default' => 12.0 ),
                    'description' => array ('type' => 'text'),
                    'id' => array ( 'type' => 'int' ),
                ),
                'indexes' => array (
                    'PRIMARY' => array ( 'type' => 'primary', 'fields' => array('id') ),
                    '_name' => array ( 'type' => 'unique', 'fields' => array('name')),
                ),
            );
            $db->adjust_table('test1', $schema2);

            $curr_schema = $db->table_schema('test1');
            $diff2 = $this->diff_array_deep($schema2, $curr_schema);
            $this->assert('check test1 schema again', count($diff2) == 0);

        }

        function test_drop_table() {
            $this->depend('create_table');

            $db = $this->db;

            $db->drop_tables(['test1', 'test2']);

            $this->assert('!table_exists(test1)', !$db->table_exists('test1'));
            $this->assert('!table_exists(test2)', !$db->table_exists('test2'));
            $this->assert('table_exists(test3)', $db->table_exists('test3'));

        }

        function teardown() {
            $db = $this->db;
            $db->empty_database();
            unlink('gini_ut.sqlite');
        }

    }

}