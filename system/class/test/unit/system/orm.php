<?php

namespace Test\Unit\System {

	class ORM extends \Model\Test\Unit {
		
		function setup() {
			_CONF('database.default', 'gini_ut');
			_CONF('database.gini_ut.url', 'mysql://genee@localhost/gini_ut');	
		}

		function test_save() {
			$o1 = new \ORM\ORM_Test3();
			$o1->save();
			echo $o1->id;
		}

		function test_object_late_binding() {
			$o1 = new \ORM\ORM_Test3(1);
			$o2 = $o1->friend;
			
			$this->assert('check o1->_uuid != o2->_uuid', 
					$this->get_property($o1, '_uuid') != $this->get_property($o2, '_uuid'));
			
			$o3 = $o2->friend;
			$this->assert('check o2->_uuid != o3->_uuid', 
					$this->get_property($o2, '_uuid') != $this->get_property($o3, '_uuid'));

			$o4 = $o2->friend;
			$this->assert('check o3->_uuid == o4->_uuid', 
					$this->get_property($o3, '_uuid') == $this->get_property($o4, '_uuid'));

		}

		function test_criteria() {
			$o1 = new \ORM\ORM_Test3();
			$o1->id = 1;
			$o2 = new \ORM\ORM_Test3(array('friend'=>$o1, 'linked'=>$o1));
			$o2->id = 2;
			$criteria1 = $o2->criteria();
			
			$expect_criteria1 = array(
				'friend_id' => $o1->id,
				'linked_name' => 'orm_test3',
				'linked_id' => $o1->id,
				);
			
			$diff1 = $this->diff_array_deep($criteria1, $expect_criteria1);
			$this->assert('check ORM_Test3 criteria', count($diff1) == 0);
		}

		function test_schema() {

			$expect_schema1 = array (
				'fields' => array (
					'name'        => array ( 'type' => 'varchar(50)' ),
					'gender'      => array ( 'type' => 'int'),
					'money'       => array ( 'type' => 'double', 'default' => '0'),
					'description' => array ('type' => 'text', 'null' => true),
					'id'          => array ( 'type' => 'bigint', 'auto_increment'=>true ),
					'_extra'      => array ( 'type' => 'text' ),
				),
				'indexes' => array (
					'PRIMARY' => array ( 'type' => 'primary', 'fields' => array('name') ),
					'_MIDX_1' => array ( 'type' => 'unique', 'fields' => array('gender','money') ),
				),
			);

			$expect_schema2 = array (
				'fields' => array (
					'name'        => array ( 'type' => 'varchar(60)' ),
					'gender'      => array ( 'type' => 'int'),
					'money'       => array ( 'type' => 'double', 'default' => '0'),
					'description' => array ( 'type' => 'text', 'null' => true),
					'id'          => array ( 'type' => 'bigint', 'auto_increment'=>true ),                                                                                                                                            
					'_extra'      => array ( 'type' => 'text' ),
				),
				'indexes' => array (
					'PRIMARY'   => array ( 'type' => 'primary', 'fields' => array('id') ),
					'_IDX_name' => array ( 'type' => 'unique', 'fields' => array('name') ),
				),
			);

			$o1      = new \ORM\ORM_Test1();
			$schema1 = $o1->schema();
			$diff1   = $this->diff_array_deep($schema1, $expect_schema1);
			$this->assert('check ORM_Test1 schema', count($diff1) == 0);

			$o2      = new \ORM\ORM_Test2();
			$schema2 = $o2->schema();
			$diff2   = $this->diff_array_deep($schema2, $expect_schema2);
			$this->assert('check ORM_Test2 schema', count($diff2) == 0);

		}

		function test_injection() {
			\ORM\ORM_Test1::inject('\ORM\ORM_Test1_Extra');

			$o1      = new \ORM\ORM_Test1();

			$structure = $this->invoke($o1, 'structure');
			$expect_structure = array(
				'name'        => array('string'=>'50'),
				'gender'      => array('bool'=>NULL),
				'money'       => array('double'=>NULL,'default'=>'0'),
				'description' => array('string'=>'*','null'=>NULL),
				'id'          => array('bigint'=>NULL,'primary'=>NULL,'auto_increment'=>NULL),
				'phone'       => array('string'=>'60'),
				'address'     => array('string'=>NULL),
				'_extra'          => array('array'=>NULL),
			);

			$diff1 = $this->diff_array_deep($structure, $expect_structure);
			$this->assert('check ORM_Test1 injection structure', count($diff1) == 0);			
		}

		function teardown() {
			\Model\Database::db()->drop_table('orm_test3');
		}

	}


}


namespace ORM {

	class ORM_Test1 extends \ORM\Object {

		var $name        = 'string:50';
		var $gender      = 'bool';
		var $money       = 'double,default:0';
		var $description = 'string:*,null';

		// index: primary:name, unique:gender,money, friend
		static $db_index = array(
			'primary:name',
			'unique:gender,money',
			);

	}

	class ORM_Test2 extends \ORM\Object {

		var $name        = 'string:60,unique';
		var $gender      = 'bool';
		var $money       = 'double,default:0';
		var $description = 'string:*,null';

	}
	
	abstract class ORM_Test1_Extra {

		var $phone   = 'string:60';
		var $address = 'string';

	}

	class ORM_Test3 extends \ORM\Object {

		var $friend 	 = 'object:orm_test3';
		var $linked		 = 'object';

	}
}

