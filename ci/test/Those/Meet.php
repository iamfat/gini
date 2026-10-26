<?php

namespace Gini\PHPUnit\Those;

class Meet extends \Gini\PHPUnit\TestCase\CLI
{
    use \Gini\PHPUnit\MockDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $db = $this->createDatabaseMock();

        $query = self::getMockBuilder('\Gini\Database\Statement')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['row'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->attachQueryHandler($db, $query);

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
                    'member' => 'many,object:user'
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
    }

    public function tearDown(): void
    {
        \Gini\IoC::clear('\Gini\ORM\User');
        \Gini\IoC::clear('\Gini\ORM\Group');
        \Gini\IoC::clear('\Gini\ORM\Room');
        \Gini\IoC::clear('\Gini\ORM\Room\Member');
        parent::tearDown();
    }

    public function testMeetWhose()
    {
        \Gini\Those::reset();
        $those = those('room/member')->whose('room.name')->contains('a')->meet(
            whose('user.name')->contains('b')
        );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."room_id","t0"."user_id" FROM "room_member" AS "t0" LEFT JOIN "room" AS "t1" ON "t0"."room_id"="t1"."id" LEFT JOIN "user" AS "t2" ON "t0"."user_id"="t2"."id" WHERE "t1"."name" LIKE \'%a%\' AND "t2"."name" LIKE \'%b%\'', $those->SQL());
    }

    public function testMeetAllOf()
    {
        \Gini\Those::reset();
        $those = those('room/member')->meet(
            allOf(
                whose('room.name')->contains('a'),
                whose('user.name')->contains('b')
            )
        );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."room_id","t0"."user_id" FROM "room_member" AS "t0" LEFT JOIN "room" AS "t1" ON "t0"."room_id"="t1"."id" LEFT JOIN "user" AS "t2" ON "t0"."user_id"="t2"."id" WHERE ("t1"."name" LIKE \'%a%\' AND "t2"."name" LIKE \'%b%\')', $those->SQL());
    }

    public function testMeetAnyOf()
    {
        \Gini\Those::reset();
        $those = those('user')->whose('name')->contains('a')->meet(
            anyOf(
                whose('id')->isIn(['1', '2', '3']),
                whose('name')->contains('g')
            )
        );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%a%\' AND ("t0"."id" IN (\'1\', \'2\', \'3\') OR "t0"."name" LIKE \'%g%\')', $those->SQL());
    }

    public function testMeetLikeAllOf()
    {
        \Gini\Those::reset();
        $those = those('room/member')->meet(
            whose('room.name')->contains('a'),
            whose('user.name')->contains('b')
        );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."room_id","t0"."user_id" FROM "room_member" AS "t0" LEFT JOIN "room" AS "t1" ON "t0"."room_id"="t1"."id" LEFT JOIN "user" AS "t2" ON "t0"."user_id"="t2"."id" WHERE ("t1"."name" LIKE \'%a%\' AND "t2"."name" LIKE \'%b%\')', $those->SQL());
    }

    public function testMeetWithForeignKeys()
    {
        \Gini\Those::reset();
        $those = those('room/member')->whose('room.name')->contains('a')->meet(
            anyOf(
                whose('user.money')->isBetween('10', '20'),
                whose('room.name')->is('c')
            )
        );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."room_id","t0"."user_id" FROM "room_member" AS "t0" LEFT JOIN "room" AS "t1" ON "t0"."room_id"="t1"."id" LEFT JOIN "user" AS "t2" ON "t0"."user_id"="t2"."id" WHERE "t1"."name" LIKE \'%a%\' AND (("t2"."money">=\'10\' AND "t2"."money"<\'20\') OR "t1"."name"=\'c\')', $those->SQL());
    }

    public function testMeetNestedCondition()
    {
        \Gini\Those::reset();
        $those = those('room/member')->whose('room.name')->contains('a')->meet(
            anyOf(
                whose('user.money')->isBetween('10', '20'),
                anyOf(
                    whose('room.name')->is('c'),
                    whose('user.name')->is('b')
                ),
                allOf(
                    whose('user')->is(a('user')),
                    whose('room')->is(null)
                )
            )
        );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."room_id","t0"."user_id" FROM "room_member" AS "t0" LEFT JOIN "room" AS "t1" ON "t0"."room_id"="t1"."id" LEFT JOIN "user" AS "t2" ON "t0"."user_id"="t2"."id" WHERE "t1"."name" LIKE \'%a%\' AND (("t2"."money">=\'10\' AND "t2"."money"<\'20\') OR ("t1"."name"=\'c\' OR "t2"."name"=\'b\') OR ("t0"."user_id"=0 AND "t1"."id" IS NULL))', $those->SQL());
    }
}
