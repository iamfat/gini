<?php

namespace Gini\PHPUnit\ORM {

    require_once __DIR__ . '/../gini.php';

    class Those extends \Gini\PHPUnit\CLI {

        public static function setUpBeforeClass() {
            parent::setUpBeforeClass();
            
            // \Gini\Config::set('database.default', [
            //     'dsn' => 'sqlite:gini_ut.sqlite3'
            // ]);    

        }
        
        public function setUp() {

            parent::setUp();

            // Mocking \ORM\UT_Lab
            //         
            // class UT_Lab extends \ORM\Object {
            // 
            //     var $name        = 'string:50';
            //     var $gender      = 'bool';
            //     var $money       = 'double,default:0';
            //     var $description = 'string:*,null';
            // 
            // }

            \Gini\IoC::bind('\ORM\UT_Lab', function()
            {
                $mock = $this->getMockBuilder('\ORM\Object')
                     ->disableOriginalConstructor()
                     ->getMock();

                $mock->expects($this->any())
                    ->method('db')
                    ->will($this->returnCallback(function(){
                        return \Gini\Database::db();
                    }));

                $labStructure = [
                    'id' => [ 'bigint' => null, 'primary' => null, 'serial' => null ],
                    '_extra' => [ 'array' => null ],
                    'name' => [ 'string' => 50 ],
                    'money' => [ 'double' => null, 'default' => 0 ],
                    'description' => [ 'string' => '*', 'null' => null ],
                ];

                $mock->expects($this->any())
                    ->method('structure')
                    ->will($this->returnValue($labStructure));
                                                
                return $mock;
            });

        }

        public function tearDown() {
            \Gini\IoC::clear('\ORM\UT_Lab');
            parent::tearDown();
        }
        
        public function testNumber() {
            
            \Gini\Those::reset();
            $those = those('ut_lab')->whose('money')->is(100);
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."money"=100', 'SQL', $those);

            \Gini\Those::reset();
            $those = those('ut_lab')->whose('money')->isNot(100);
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."money"<>100', 'SQL', $those);

            \Gini\Those::reset();
            $those = those('ut_lab')->whose('money')->isGreaterThan(100);
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."money">100', 'SQL', $those);

            \Gini\Those::reset();
            $those = those('ut_lab')->whose('money')->isLessThan(100);
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."money"<100', 'SQL', $those);

            \Gini\Those::reset();
            $those = those('ut_lab')->whose('money')->isGreaterThanOrEqual(100);
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."money">=100', 'SQL', $those);

            \Gini\Those::reset();
            $those = those('ut_lab')->whose('money')->isLessThanOrEqual(100);
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."money"<=100', 'SQL', $those);

            \Gini\Those::reset();
            $those = those('ut_lab')->whose('money')->isBetween(100, 200);
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE ("t0"."money">=100 AND "t0"."money"<200)', 'SQL', $those);
        }
        
        public function testStringMatch() {
            
            \Gini\Those::reset();
            $those = those('ut_lab')->whose('name')->beginsWith('COOL');
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', 'SQL', $those);


            \Gini\Those::reset();
            $those = those('ut_lab')->whose('name')->contains('COOL');
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."name" LIKE \'%COOL%\'', 'SQL', $those);


            \Gini\Those::reset();
            $those = those('ut_lab')->whose('name')->endsWith('COOL');
            $those->makeSQL();
            $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "ut_lab" AS "t0" WHERE "t0"."name" LIKE \'%COOL\'', 'SQL', $those);

        }
    
        public static function tearDownAfterClass() {
            parent::tearDownAfterClass();
            if (file_exists('gini_ut.sqlite3')) {
                unlink('gini_ut.sqlite3');
            }
        }
            
    }
        
}

