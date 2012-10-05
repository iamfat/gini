<?php

namespace Test\Unit\System;

class Database extends \Model\Test\Unit {

	var $db;

	function setup() {
		_CONF('database.gini_ut.url', 'mysql://genee@localhost/gini_ut');	
		$this->db = \Model\Database::db('gini_ut');
	}

	function test_config() {
		$db = $this->db;

		$url = $this->get_property($db, '_url');
		$this->assert('db[url]', $url == 'mysql://genee@localhost/gini_ut');

	}

	function test_create_table() {
		$db = $this->db;

		$db->create_table('test1');
		$db->create_table('test2:innodb');
		$db->create_table('test3:myisam');

		$this->assert('table_exists(test1)', $db->table_exists('test1'));
		$this->assert('table_exists(test2)', $db->table_exists('test2'));
		$this->assert('table_exists(test3)', $db->table_exists('test3'));

		$this->assert('table_engine(test1) == innodb', $db->table_status('test1')->engine == 'innodb');
		$this->assert('table_engine(test2) == innodb', $db->table_status('test2')->engine == 'innodb');
		$this->assert('table_engine(test3) == myisam', $db->table_status('test3')->engine == 'myisam');

	}

	function test_adjust_table() {
		$this->depend('create_table');

		$db = $this->db;

		$schema1 = array (
			'fields' => array (
				'name' => array ( 'type' => 'varchar(60)' ),
				'gender' => array ( 'type' => 'int'),
				'money' => array ( 'type' => 'double', 'default' => '0'),
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
				'money' => array ( 'type' => 'double', 'default' => '12'),
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

		$db->drop_table('test1', 'test2');

		$this->assert('!table_exists(test1)', !$db->table_exists('test1'));
		$this->assert('!table_exists(test2)', !$db->table_exists('test2'));
		$this->assert('table_exists(test3)', $db->table_exists('test3'));

	}

	function teardown() {
		$db = $this->db;

		$db->empty_database();
	}

}
