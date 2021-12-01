<?php

namespace Gini\PHPUnit\Those;

class Those extends \Gini\PHPUnit\TestCase\CLI
{
    private $resultRows = [];

    public function setUp(): void
    {
        parent::setUp();

        $db = self::getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['query', 'quote', 'quoteIdent', 'ident'])
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

        $result = self::getMockBuilder('\Gini\Database\Statement')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['row'])
            ->disableOriginalConstructor()
            ->getMock();

        $result->expects($this->any())
            ->method('row')
            ->will($this->returnCallback(function () {
                $r = current($this->resultRows);
                next($this->resultRows);
                return $r;
            }));

        $db->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($s, $s1, $s2) use ($result) {
                return $result;
            }));

        \Gini\IoC::bind('\Gini\ORM\User', function ($criteria = null) use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'ownProperties', 'name', 'tableName'])
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
                ->method('ownProperties')
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

            unset($o->id);
            unset($o->_extra);
            if (isset($criteria)) {
                $criteria = $o->normalizeCriteria($o->criteria($criteria));
                $o->setData($criteria);
            }

            return $o;
        });

        $plurals = \Gini\Config::get('orm.plurals');
        $plurals['users'] = 'user';
        \Gini\Config::set('orm.plurals', $plurals);
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
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"=100', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isNot(100);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"<>100', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isGreaterThan(100);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money">100', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isLessThan(100);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"<100', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isGreaterThanOrEqual(100);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money">=100', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isLessThanOrEqual(100);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"<=100', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('money')->isBetween(100, 200);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE ("t0"."money">=100 AND "t0"."money"<200)', $those->SQL());
    }

    public function testOrderBy()
    {
        \Gini\Those::reset();
        $those = those('user')->orderBy('gender');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" ORDER BY "t0"."gender" ASC', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->orderBy('friend.gender');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" LEFT JOIN "user" AS "t2" ON "t1"."friend_id"="t2"."id" ORDER BY "t2"."gender" ASC', $those->SQL());
    }

    public function testStringMatch()
    {
        \Gini\Those::reset();
        $those = those('user')->whose('name')->beginsWith('COOL');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('name')->contains('COOL');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%COOL%\'', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')->whose('name')->endsWith('COOL');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%COOL\'', $those->SQL());
    }

    public function testPlurals()
    {
        // e.g. those('users')
        \Gini\Those::reset();
        $those = those('users')->whose('name')->beginsWith('COOL');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', $those->SQL());
    }

    public function testInObjects()
    {
        // e.g. those('users')
        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->andWhose('father')->isIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" AND "t1"."name"=\'A\' WHERE "t0"."gender"=1 AND "t1"."id" IS NOT NULL', $those->SQL());

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->orWhose('father')->isIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" AND "t1"."name"=\'A\' WHERE "t0"."gender"=1 OR "t1"."id" IS NOT NULL', $those->SQL());

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)
            ->andWhose('father')->isNotIn(those('users')->whose('name')->is('A'));
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" AND "t1"."name"=\'A\' WHERE "t0"."gender"=1 AND "t1"."id" IS NULL', $those->SQL());
    }

    public function testWithOne2OneRelationship()
    {
        $user = a('user');
        \Gini\Those::reset();
        $those = those('users')->whose('father.name')->is('A');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id" WHERE "t1"."name"=\'A\'', $those->SQL());
    }

    public function testWithMany()
    {
        \Gini\Those::reset();

        $user = a('user');
        $those = those('users')->whose('gender')->is(1)->andWhose('friend')->is($user);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" WHERE "t0"."gender"=1 AND "t1"."friend_id"=0', $those->SQL());

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('relation')->is($user);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "_user_relation" AS "t1" ON "t1"."user_id"="t0"."id" WHERE "t0"."gender"=1 AND ("t1"."relation_name"=\'user\' AND "t1"."relation_id"=0)', $those->SQL());

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('prop')->is(10);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "_user_prop" AS "t1" ON "t1"."user_id"="t0"."id" WHERE "t0"."gender"=1 AND "t1"."prop"=10', $those->SQL());

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('friend.gender')->is(0)->andWhose('friend.friend.money')->isGreaterThan(100);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" LEFT JOIN "user" AS "t2" ON "t1"."friend_id"="t2"."id" LEFT JOIN "_user_friend" AS "t3" ON "t3"."user_id"="t2"."id" LEFT JOIN "user" AS "t4" ON "t3"."friend_id"="t4"."id" WHERE "t0"."gender"=1 AND "t2"."gender"=0 AND "t4"."money">100', $those->SQL());

        \Gini\Those::reset();
        $those = those('users')->whose('gender')->is(1)->andWhose('friend.friend')->is($user);
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "_user_friend" AS "t1" ON "t1"."user_id"="t0"."id" LEFT JOIN "user" AS "t2" ON "t1"."friend_id"="t2"."id" LEFT JOIN "_user_friend" AS "t3" ON "t3"."user_id"="t2"."id" WHERE "t0"."gender"=1 AND "t3"."friend_id"=0', $those->SQL());
    }

    public function testGet()
    {
        \Gini\Those::reset();
        $this->resultRows = [
            ['id' => 20, 'name' => 'a'],
            ['id' => 30, 'name' => 'b'],
            ['id' => 40, 'name' => 'c'],
            ['id' => 50, 'name' => 'd'],
        ];
        $those1 = those('user')->get('name');
        self::assertEquals([20 => 'a', 30 => 'b', 40 => 'c', 50 => 'd'], $those1);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->is('test');
        $this->resultRows = [];
        $res = $those->get('name');
        self::assertEquals([], $res);

        \Gini\Those::reset();
        $those = those('user')->whose('name')->is('a');
        $this->resultRows = [
            ['id' => 20, 'name' => 'a'],
        ];
        $res = $those->get('name');
        self::assertEquals([20 => 'a'], $res);

        \Gini\Those::reset();
        $this->resultRows = [
            ['name' => 'a', 'description' => 'temp1'],
            ['name' => 'b', 'description' => 'temp2'],
            ['name' => 'c', 'description' => 'temp3'],
            ['name' => 'd', 'description' => 'temp4'],
        ];
        $res = those('user')->get('name', 'description');
        self::assertEquals(['a' => 'temp1', 'b' => 'temp2', 'c' => 'temp3', 'd' => 'temp4'], $res);

        \Gini\Those::reset();
        $this->resultRows = [
            ['id' => 20, 'name' => 'a', 'description' => 'temp1'],
            ['id' => 30, 'name' => 'b', 'description' => 'temp2'],
            ['id' => 40, 'name' => 'c', 'description' => 'temp3'],
            ['id' => 50, 'name' => 'd', 'description' => 'temp4'],
        ];
        $res = those('user')->get(['name', 'description']);
        self::assertEquals([
            20 => ['name' => 'a', 'description' => 'temp1'],
            30 => ['name' => 'b', 'description' => 'temp2'],
            40 => ['name' => 'c', 'description' => 'temp3'],
            50 => ['name' => 'd', 'description' => 'temp4']
        ], $res);

        \Gini\Those::reset();
        $this->resultRows = [
            ['id' => 20, 'name' => 'a', 'description' => 'temp1'],
            ['id' => 30, 'name' => 'b', 'description' => 'temp2'],
            ['id' => 40, 'name' => 'c', 'description' => 'temp3'],
            ['id' => 50, 'name' => 'd', 'description' => 'temp4'],
        ];
        $res = those('user')->get('name', ['id', 'description']);
        self::assertEquals([
            'a' => ['id' => 20, 'description' => 'temp1'],
            'b' => ['id' => 30, 'description' => 'temp2'],
            'c' => ['id' => 40, 'description' => 'temp3'],
            'd' => ['id' => 50, 'description' => 'temp4']
        ], $res);

        \Gini\Those::reset();
        $this->resultRows = [
            ['id' => 20, 'father_id' => 30],
            ['id' => 30, 'father_id' => null],
            ['id' => 40, 'father_id' => 50],
            ['id' => 50, 'father_id' => null],
        ];
        $res = those('user')->get('father');
        self::assertEquals(['id' => 30], $res[20]->criteria());
        self::assertEquals(null, $res[30]->id);
        self::assertEquals(['id' => 50], $res[40]->criteria());
        self::assertEquals(null, $res[50]->id);
    }
}
