<?php

namespace Unit\System {

	class ORM extends \Model\Unit {
		

		function setup() {
		}

		function test_schema() {

			$expect_schema1 = array (
				'fields' => array (
					'name'        => array ( 'type' => 'varchar(50)' ),
					'gender'      => array ( 'type' => 'int'),
					'money'       => array ( 'type' => 'double', 'default' => '0'),
					'description' => array ('type' => 'text', 'null' => true),
					'id'          => array ( 'type' => 'int' ),
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
					'id'          => array ( 'type' => 'int' ),                                                                                                                                            
				),
				'indexes' => array (
					'PRIMARY'   => array ( 'type' => 'primary', 'fields' => array('id') ),
					'_IDX_name' => array ( 'type' => 'unique', 'fields' => array('name') ),
				),
			);

			$o1      = new \Model\ORM\ORM_Test1();
			$schema1 = $o1->schema();
			$diff1   = $this->diff_array_deep($schema1, $expect_schema1);
			$this->assert('check ORM_Test1 schema', count($diff1) == 0);

			$o2      = new \Model\ORM\ORM_Test2();
			$schema2 = $o2->schema();
			$diff2   = $this->diff_array_deep($schema2, $expect_schema2);
			$this->assert('check ORM_Test2 schema', count($diff2) == 0);

		}

		function test_injection() {
			\Model\ORM\ORM_Test1::inject('\Model\ORM\ORM_Test1_Extra');

			$o1      = new \Model\ORM\ORM_Test1();

			$structure = $this->invoke($o1, 'structure');
			$expect_structure = array(
				'name'        => 'string:50',
				'gender'      => 'bool',
				'money'       => 'double,default:0',
				'description' => 'string:*,null',
				'id'          => 'int,primary',
				'phone'       => 'string:60',
				'address'     => 'string',
			);

			$diff1 = $this->diff_array_deep($structure, $expect_structure);
			$this->assert('check ORM_Test1 injection structure', count($diff1) == 0);			
		}

		function teardown() {

		}

	}


}


namespace Model\ORM {

	class ORM_Test1 extends \Model\ORM\Object {

		var $name        = 'string:50';
		var $gender      = 'bool';
		var $money       = 'double,default:0';
		var $description = 'string:*,null';

		static $db_index = array(
			'primary:name',
			'unique:gender,money',
			);

	}

	class ORM_Test2 extends \Model\ORM\Object {

		var $name        = 'string:60,unique';
		var $gender      = 'bool';
		var $money       = 'double,default:0';
		var $description = 'string:*,null';

	}
	
	abstract class ORM_Test1_Extra {

		var $phone   = 'string:60';
		var $address = 'string';

	}
}

