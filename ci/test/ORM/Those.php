<?php

namespace Gini\PHPUnit\ORM;

class Those extends \Gini\PHPUnit\TestCase\CLI
{
    public function setUp(): void
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
            $o = $this->getMockBuilder('\Gini\ORM\Base')
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

    public function tearDown(): void
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

    public function testOrderBy()
    {
        \Gini\Those::reset();
        $those = those('user')->orderBy('gender');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" ORDER BY "t0"."gender" ASC', 'SQL', $those);

        \Gini\Those::reset();
        $those = those('user')->orderBy('friend.gender');
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" INNER JOIN "user" AS "t2" ON "t1"."friend_id"="t2"."id" ORDER BY "t2"."gender" ASC', 'SQL', $those);
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
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" AND "t1"."name"=\'A\' WHERE "t0"."gender"=1 AND "t1"."id" IS NULL', 'SQL', $those);

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
            $o = $this->getMockBuilder('\Gini\ORM\Base')
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
            $o = $this->getMockBuilder('\Gini\ORM\Base')
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

    public function testGet()
    {

        $db = self::getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['query', 'quote', 'quoteIdent', 'ident'])
            ->disableOriginalConstructor()
            ->getMock();

        $query = self::getMockBuilder('\Gini\Database\Statement')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['row'])
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

                return '"' . addslashes($s) . '"';
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

                return '\'' . addslashes($s) . '\'';
            }));


        $db->expects($this->any())
            ->method('ident')
            ->will($this->returnCallback(
                function ($s) use ($db) {

                    $args = func_get_args();
                    $ident = array();
                    foreach ($args as $arg) {
                        $ident[] = $db->quoteIdent($arg);
                    }

                    return implode('.', $ident);
                }
            ));


        $db->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($s, $s1, $s2) use ($query) {
                return $query;
            }));
        $test_rows = [
            ['id' => 20, 'name' => 'a', 'money' => 1.0, 'father_id' => 30, 'description' => 'temp1'],
            ['id' => 30, 'name' => 'b', 'money' => 2.0, 'father_id' => null, 'description' => 'temp2'],
            ['id' => 40, 'name' => 'c', 'money' => 3.0, 'father_id' => 50, 'description' => 'temp3'],
            ['id' => 50, 'name' => 'd', 'money' => 4.0, 'father_id' => null, 'description' => 'temp4'],
        ];
        $row_num = 0;
        $query->expects($this->any())
            ->method('row')
            ->will($this->returnCallback(function ($s) use (&$test_rows, &$row_num) {
                if (isset($test_rows[$row_num])) {
                    $res = $test_rows[$row_num];
                    $row_num++;
                    return $res;
                } else {
                    return false;
                }
            }));


        \Gini\IoC::bind('\Gini\ORM\User', function ($criteria) use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
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
                    'description' => 'string:*,null'
                ]));
            $o->criteria = $criteria;

            return $o;
        });
        \Gini\Those::reset();
        $test_rows = [
            ['id' => 20, 'name' => 'a'],
            ['id' => 30, 'name' => 'b'],
            ['id' => 40, 'name' => 'c'],
            ['id' => 50, 'name' => 'd'],
        ];
        $those1 = those('user')->get('name');
        $this->assertEquals([20 => 'a', 30 => 'b', 40 => 'c', 50 => 'd'], $those1);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->is('test');
        $test_rows = [
        ];
        $row_num = 0;
        $res = $those->get('name');
        $this->assertEquals([], $res);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->is('a');
        $test_rows = [
            ['id' => 20, 'name' => 'a'],
        ];
        $row_num = 0;
        $res = $those->get('name');
        $this->assertEquals([20 => 'a'], $res);

        \Gini\Those::reset();
        $row_num = 0;
        $test_rows = [
            ['name' => 'a', 'description' => 'temp1'],
            ['name' => 'b', 'description' => 'temp2'],
            ['name' => 'c', 'description' => 'temp3'],
            ['name' => 'd', 'description' => 'temp4'],
        ];
        $res = those('user')->get('name', 'description');
        $this->assertEquals(['a' => 'temp1', 'b' => 'temp2', 'c' => 'temp3', 'd' => 'temp4'], $res);

        \Gini\Those::reset();
        $row_num = 0;
        $test_rows = [
            ['id' => 20, 'name' => 'a', 'description' => 'temp1'],
            ['id' => 30, 'name' => 'b', 'description' => 'temp2'],
            ['id' => 40, 'name' => 'c', 'description' => 'temp3'],
            ['id' => 50, 'name' => 'd', 'description' => 'temp4'],
        ];
        $res = those('user')->get(['name', 'description']);
        $this->assertEquals([
            20 => ['name' => 'a', 'description' => 'temp1'],
            30 => ['name' => 'b', 'description' => 'temp2'],
            40 => ['name' => 'c', 'description' => 'temp3'],
            50 => ['name' => 'd', 'description' => 'temp4']
        ], $res);

        \Gini\Those::reset();
        $row_num = 0;
        $test_rows = [
            ['id' => 20, 'name' => 'a', 'description' => 'temp1'],
            ['id' => 30, 'name' => 'b', 'description' => 'temp2'],
            ['id' => 40, 'name' => 'c', 'description' => 'temp3'],
            ['id' => 50, 'name' => 'd', 'description' => 'temp4'],
        ];
        $res = those('user')->get('name', ['id', 'description']);
        $this->assertEquals([
            'a' => ['id' => 20, 'description' => 'temp1'],
            'b' => ['id' => 30, 'description' => 'temp2'],
            'c' => ['id' => 40, 'description' => 'temp3'],
            'd' => ['id' => 50, 'description' => 'temp4']
        ], $res);

        \Gini\Those::reset();
        $row_num = 0;
        $test_rows = [
            ['id' => 20, 'father_id' => 30],
            ['id' => 30, 'father_id' => null],
            ['id' => 40, 'father_id' => 50],
            ['id' => 50, 'father_id' => null],
        ];
        $res = those('user')->get('father');
        $this->assertEquals(30, $res['20']->criteria);
        $this->assertEquals(null, $res['30']->id);
        $this->assertEquals(50, $res['40']->criteria);
        $this->assertEquals(null, $res['50']->id);
    }

    public function testWhoAre(){

        $db = self::getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['query', 'quote', 'quoteIdent', 'ident'])
            ->disableOriginalConstructor()
            ->getMock();

        $query = self::getMockBuilder('\Gini\Database\Statement')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['row'])
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

                return '"' . addslashes($s) . '"';
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

                return '\'' . addslashes($s) . '\'';
            }));


        $db->expects($this->any())
            ->method('ident')
            ->will($this->returnCallback(
                function ($s) use ($db) {

                    $args = func_get_args();
                    $ident = array();
                    foreach ($args as $arg) {
                        $ident[] = $db->quoteIdent($arg);
                    }

                    return implode('.', $ident);
                }
            ));


        $db->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($s, $s1, $s2) use ($query) {
                return $query;
            }));

        \Gini\IoC::bind('\Gini\ORM\User', function ($criteria) use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
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
                    'description' => 'string:*,null'
                ]));
            $o->criteria = $criteria;

            return $o;
        });

        \Gini\IoC::bind('\Gini\ORM\Group', function ($criteria) use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('group'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('group'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'name' => 'string:50',
                    'member' => 'many:user,object:user'
                ]));
            $o->criteria = $criteria;

            return $o;
        });

        \Gini\IoC::bind('\Gini\ORM\Room', function ($criteria) use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('room'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('room'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'name' => 'string:50'
                ]));
            $o->criteria = $criteria;
            return $o;
        });

        \Gini\IoC::bind('\Gini\ORM\Room\Member', function ($criteria) use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('room/member'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('room_member'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'room' => 'object:room',
                    'user' => 'object:user',
                ]));
            $o->criteria = $criteria;
            return $o;
        });

        \Gini\Those::reset();
        $those = those('user')->whoAre('group.member')->of(those('group')->whose('name')->contains('f'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "_group_member" AS "t2" ON "t2"."member_id"="t0"."id" INNER JOIN "group" AS "t3" ON "t3"."member_id"="t2"."group_id" WHERE "t3"."name" LIKE \'%f%\'','SQL',$those);

        \Gini\Those::reset();
        $those = those('user')->whose('father.name')->is('g')->whoAre('group.member')->of(those('group')->whose('name')->contains('f'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" INNER JOIN "_group_member" AS "t3" ON "t3"."member_id"="t0"."id" INNER JOIN "group" AS "t4" ON "t4"."member_id"="t3"."group_id" WHERE "t1"."name"=\'g\' AND "t4"."name" LIKE \'%f%\'','SQL',$those);

        \Gini\Those::reset();
        $those = those('user')->whose('father.name')->is('g')->whoAre('room/member.user')->of(those('room/member')->whose('room.name')->contains('f'));
        $those->makeSQL();
        $this->assertAttributeEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" INNER JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" INNER JOIN "room_member" AS "t4" ON "t4"."user_id"="t0"."id" INNER JOIN "room" AS "t5" ON "t4"."room_id"="t5"."id" WHERE "t1"."name"=\'g\' AND "t5"."name" LIKE \'%f%\'','SQL',$those);


    }
}

