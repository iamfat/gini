<?php

namespace Gini\PHPUnit\ORM;

class Those extends \Gini\PHPUnit\TestCase\CLI
{
    public function setUp()
    {
        parent::setUp();

        $db = self::getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_'.uniqid())
            ->setMethods(['query', 'quote', 'quoteIdent'])
            ->disableOriginalConstructor()
            ->getMock();

        $db->expects($this->any())
            ->method('quoteIdent')
            ->will($this->returnCallback(function ($s) use ($db) {
                if (is_array($s)) {
                    foreach ($s as &$i) {
                        $i = $db->quoteIdent($i);
                    }

                    return implode(',', $s);
                }

                return '"'.addslashes($s).'"';
            }));

        $db->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function ($s) use ($db) {
                if (is_array($s)) {
                    foreach ($s as &$i) {
                        $i = $db->quote($i);
                    }

                    return implode(',', $s);
                } elseif (is_null($s)) {
                    return 'NULL';
                } elseif (is_bool($s)) {
                    return $s ? 1 : 0;
                } elseif (is_int($s) || is_float($s)) {
                    return $s;
                }

                return '\''.addslashes($s).'\'';
            }));

        \Gini\IoC::bind('\Gini\ORM\User', function () use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Object')
                ->setMockClassName('MOBJ_'.uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('user'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('user'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                'id' => 'bigint,pimary,serial',
                '_extra' => 'array',
                'name' => 'string:50',
                'money' => 'double,default:0',
                'father' => 'object:user',
                'description' => 'string:*,null',
                'friend' => 'object:user,many',
                'relation' => 'object,many',
                'prop' => 'bigint,many'
            ]));

            return $o;
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
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"=100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isNot(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"<>100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isGreaterThan(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money">100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isLessThan(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"<100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isGreaterThanOrEqual(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money">=100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isLessThanOrEqual(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"<=100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isBetween(100, 200);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE ("t0"."money">=100 AND "t0"."money"<200)', 'SQL', $those);
    }

    public function testStringMatch()
    {
        \Gini\Those::reset();
        $those = those('user')->whose('name')->beginsWith('COOL');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->contains('COOL');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%COOL%\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->endsWith('COOL');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%COOL\'', 'SQL', $those);
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
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', 'SQL', $those);

    }

    public function testInObjects() 
    {
        // e.g. those('users')
        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->andWhose('father')->isIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t0"."gender"=1 AND "t1"."name"=\'A\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->orWhose('father')->isIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t0"."gender"=1 OR "t1"."name"=\'A\'', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->andWhose('father')->isNotIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t0"."gender"=1 AND "t0"."father_id" IS NOT NULL AND "t1"."name"=\'A\'', 'SQL', $those);

    }

    public function testWithOne2OneRelationship() {
        $user = a('user');
        \Gini\Those::reset();
        $those = those('users')->whose('father.name')->is('A');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t1"."name"=\'A\'', 'SQL', $those);
    }

    public function testWithMany() 
    {
        $user = a('user');
        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('friend')->is($user);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" WHERE "t0"."gender"=1 AND "t1"."friend_id"=0', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('relation')->is($user);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "_user_relation" AS "t1" ON "t1"."user_id"="t0"."id" WHERE "t0"."gender"=1 AND ("t1"."relation_name"=\'user\' AND "t1"."relation_id"=0)', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('prop')->is(10);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "_user_prop" AS "t1" ON "t1"."user_id"="t0"."id" WHERE "t0"."gender"=1 AND "t1"."prop"=10', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('friend.gender')->is(0)->andWhose('friend.friend.money')->isGreaterThan(100);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" INNER JOIN "user" AS "t2" ON "t1"."friend_id"="t2"."id" INNER JOIN "_user_friend" AS "t3" ON "t3"."user_id"="t2"."id" INNER JOIN "user" AS "t4" ON "t3"."friend_id"="t4"."id" WHERE "t0"."gender"=1 AND "t2"."gender"=0 AND "t4"."money">100', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('friend.friend')->is($user);
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" INNER JOIN "user" AS "t2" ON "t1"."friend_id"="t2"."id" INNER JOIN "_user_friend" AS "t3" ON "t3"."user_id"="t2"."id" WHERE "t0"."gender"=1 AND "t3"."friend_id"=0', 'SQL', $those);
    }

    public function testFieldOfField(){

        $db = self::getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_'.uniqid())
            ->setMethods(['query', 'quote', 'quoteIdent'])
            ->disableOriginalConstructor()
            ->getMock();

        $db->expects($this->any())
            ->method('quoteIdent')
            ->will($this->returnCallback(function ($s) use ($db) {
                if (is_array($s)) {
                    foreach ($s as &$i) {
                        $i = $db->quoteIdent($i);
                    }

                    return implode(',', $s);
                }

                return '"'.addslashes($s).'"';
            }));

        $db->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function ($s) use ($db) {
                if (is_array($s)) {
                    foreach ($s as &$i) {
                        $i = $db->quote($i);
                    }

                    return implode(',', $s);
                } elseif (is_null($s)) {
                    return 'NULL';
                } elseif (is_bool($s)) {
                    return $s ? 1 : 0;
                } elseif (is_int($s) || is_float($s)) {
                    return $s;
                }

                return '\''.addslashes($s).'\'';
            }));

        \Gini\IoC::bind('\Gini\ORM\Company', function () use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Object')
                ->setMockClassName('MOBJ_'.uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('company'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('company'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'name' => 'string:50',
                    'type' => 'object:company/type'
                ]));

            return $o;
        });

        \Gini\IoC::bind('\Gini\ORM\Company\Type', function () use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Object')
                ->setMockClassName('MOBJ_'.uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('company/type'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('company_type'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'name' => 'string:50'
                ]));

            return $o;
        });

        \Gini\Those::reset();
        $those = those('company')->whose('type.name')->is('test');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."type_id" FROM "company" AS "t0" INNER JOIN "company_type" AS "t1" ON "t0"."type_id"="t1"."id" WHERE "t1"."name"=\'test\'','SQL',$those);
    }
}
