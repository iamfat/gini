<?php

namespace Test\Unit\System {

    class ORM extends \Model\Test\Unit {
        
        function setup() {
            _CONF('database.default', 'gini_ut');
            _CONF('database.gini_ut.url', 'sqlite3://gini_ut.sqlite');    
        }

        function test_save() {
            $o1 = new \ORM\ORM_Test3();
            $o1->inheritance();
            $o1->db()->adjust_table($o1->table_name(), $o1->schema());

            $o1->name = "Hello";
            $o1->gender = 1;
            $o1->save();

            $this->assert('check o1 save', $o1->id > 0);

            $o1->save();
            $this->assert('check o1 save', $o1->id > 0);

        }

        function test_extra() {
            $o1 = new \ORM\ORM_Test3();
            $o1->name = "Hello";
            $o1->extra_property = "How are you?";
            $o1->save();

            $this->assert('check o1->extra_property: '.$o1->extra_property, $o1->extra_property == "How are you?");

            $o2 = new \ORM\ORM_Test3($o1->id);
            $this->assert('check o1->extra_property == o2->extra_property ', $o1->extra_property == $o2->extra_property);
        }

        function test_object_late_binding() {
            $o1 = new \ORM\ORM_Test3(1);
            $o2 = $o1->friend;

            $this->assert('check o1 !== o2', 
                    $o1 !== $o2);
        
            $o3 = $o2->friend;
            $this->assert('check o2 !== o3', 
                    $o2 !== $o3);

            $o4 = $o2->friend;
            $this->assert('check o3 === o4', 
                    $o3 == $o4);

        }

        function test_criteria() {
            $o1 = new \ORM\ORM_Test3();
            $o1->id = 1;
            $o2 = new \ORM\ORM_Test3(array('friend'=>$o1, 'linked'=>$o1));
            $o2->id = 2;
            $criteria1 = $o2->normalize_criteria($o2->criteria());

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
                    'id'          => array ( 'type' => 'bigint', 'serial'=>true ),
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
                    'id'          => array ( 'type' => 'bigint', 'serial'=>true ),                                                                                                                                            
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
                'gender'      => array('bool'=>null),
                'money'       => array('double'=>null,'default'=>'0'),
                'description' => array('string'=>'*','null'=>null),
                'id'          => array('bigint'=>null,'primary'=>null,'serial'=>null),
                'phone'       => array('string'=>'60'),
                'address'     => array('string'=>null),
                '_extra'          => array('array'=>null),
            );

            $diff1 = $this->diff_array_deep($structure, $expect_structure);
            $this->assert('check ORM_Test1 injection structure', count($diff1) == 0);            
        }

        function teardown() {
            \Model\Database::db()->drop_table('orm_test3');
            unlink('gini_ut.sqlite');
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

        var $name        = 'string:50';
        var $gender      = 'bool';
        var $friend      = 'object:orm_test3';
        var $linked         = 'object';

    }
}

