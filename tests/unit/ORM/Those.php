<?php

namespace Gini\PHPUnit\ORM;

require_once __DIR__.'/../gini.php';

class Those extends \Gini\PHPUnit\CLI
{
    public function setUp()
    {
        parent::setUp();

        $db = \Gini\IoC::construct('\Gini\Database', 'sqlite::memory:');

        \Gini\IoC::bind('\Gini\ORM\User', function () use ($db) {
            $user = $this->getMockBuilder('\Gini\ORM\Object')
                 ->disableOriginalConstructor()
                 ->getMock();

            $user->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $user->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                'id' => 'bigint,pimary,serial',
                '_extra' => 'array',
                'name' => 'string:50',
                'money' => 'double,default:0',
                'father' => 'object:user',
                'description' => 'string:*,null',
            ]));

            return $user;
        });

    }

    public function tearDown()
    {
        \Gini\IoC::clear('\Gini\ORM\User');
        parent::tearDown();
    }

    public function testNumber()
    {
        \Gini\Those::reset();
        $those = those('user')->whose('money')->is(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."money"=100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isNot(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."money"<>100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isGreaterThan(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."money">100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isLessThan(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."money"<100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isGreaterThanOrEqual(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."money">=100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isLessThanOrEqual(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."money"<=100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isBetween(100, 200);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE ("t0"."money">=100 AND "t0"."money"<200)', 'SQL', $those);
    }

    public function testStringMatch()
    {
        \Gini\Those::reset();
        $those = those('user')->whose('name')->beginsWith('COOL');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->contains('COOL');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%COOL%\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->endsWith('COOL');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%COOL\'', 'SQL', $those);
    }

    public function testPlurals()
    {
        $plurals = \Gini\Config::get('orm.plurals');
        $plurals['users'] = 'user';
        \Gini\Config::set('orm.plurals', $plurals);
        
        // e.g. those('users')
        \Gini\Those::reset();
        $those = those('users')->whose('name')->beginsWith('COOL');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', 'SQL', $those);

    }

    public function testInObjects() 
    {
        // e.g. those('users')
        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->andWhose('father')->isIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" INNER JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t0"."gender"=1 AND "t1"."name"=\'A\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->orWhose('father')->isIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" INNER JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t0"."gender"=1 OR "t1"."name"=\'A\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->andWhose('father')->isNotIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t0"."gender"=1 AND "t0"."father_id" IS NOT NULL AND "t1"."name"=\'A\'', 'SQL', $those);

    }

}
