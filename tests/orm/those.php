<?php

namespace Gini\PHPUnit\ORM;
require_once __DIR__ . '/../gini.php';

class Those extends \Gini\PHPUnit\CLI {

    public function setUp() {

        parent::setUp();

        // $db = $this->getMockBuilder('\Gini\Database')
        //      ->disableOriginalConstructor()
        //      ->getMock();
        // 
        // $db->expects($this->any())
        //     ->method('quote')
        //     ->will($this->returnCallback(function($s) use($db) {
        //         if (is_array($s)) {
        //             foreach ($s as &$i) {
        //                 $i = $db->quote($i);
        //             }
        // 
        //             return implode(',', $s);
        //         } elseif (is_null($s)) {
        //             return 'NULL';
        //         } elseif (is_bool($s)) {
        //             return $s ? 1 : 0;
        //         } elseif (is_int($s) || is_float($s)) {
        //             return $s;
        //         }
        // 
        //         return '\''.addslashes($s).'\'';
        //     }));
        // 
        // $db->expects($this->any())
        //     ->method('quoteIdent')
        //     ->will($this->returnCallback(function($s) use($db) {
        //         if (is_array($s)) {
        //             foreach ($s as &$i) {
        //                 $i = $mock->quoteIdent($i);
        //             }
        // 
        //             return implode(',', $s);
        //         }
        // 
        //         return '"'.addslashes($s).'"';
        //     }));
        // 
        // $db->expects($this->any())
        //     ->method('ident')
        //     ->will($this->returnCallback(function() {
        //         $args = func_get_args();
        //         $ident = [];
        //         foreach ($args as $arg) {
        //             $ident[] = '"'.addslashes($arg).'"';
        //         }
        //         return implode('.', $ident);
        //    }));
        
        $db = \Gini\IoC::construct('\Gini\Database', 'sqlite::memory:');

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

        \Gini\IoC::bind('\ORM\UT_Lab', function() use ($db) {
            $lab = $this->getMockBuilder('\ORM\Object')
                 ->disableOriginalConstructor()
                 ->getMock();

            $lab->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $labStructure = [
                'id' => [ 'bigint' => null, 'primary' => null, 'serial' => null ],
                '_extra' => [ 'array' => null ],
                'name' => [ 'string' => 50 ],
                'money' => [ 'double' => null, 'default' => 0 ],
                'description' => [ 'string' => '*', 'null' => null ],
            ];

            $lab->expects($this->any())
                ->method('structure')
                ->will($this->returnValue($labStructure));
                                            
            return $lab;
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

}
