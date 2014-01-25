<?php

namespace Gini\PHPUnit\ORM {

    require_once __DIR__ . '/../gini.php';

    class Those extends \Gini\PHPUnit\CLI {

        public static function setUpBeforeClass() {
            parent::setUpBeforeClass();
            
            _CONF('database.default', [
                'dsn' => 'sqlite:gini_ut.sqlite3'
            ]);    

            class_exists('\\Gini\\Those');

            $fakeORM = <<<'EOT'
                namespace ORM;

                class UT_User extends \ORM\Object {

                    var $name        = 'string:50';

                }

                class UT_Department extends \ORM\Object {

                    var $name        = 'string:50';

                }

                class UT_Account extends \ORM\Object {

                    var $lab = 'object:lab';
                    var $department = 'object:department';

                }

                class UT_Lab extends \ORM\Object {

                    var $name        = 'string:50';
                    var $gender      = 'bool';
                    var $money       = 'double,default:0';
                    var $description = 'string:*,null';

                }

EOT;

            eval($fakeORM);
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

