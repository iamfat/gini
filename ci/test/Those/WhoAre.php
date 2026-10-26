<?php

namespace Gini\PHPUnit\Those;

class WhoAre extends \Gini\PHPUnit\TestCase\CLI
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
                    'name' => 'string:50',
                    'member' => 'many,object:user,via:room/member'
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

        \Gini\IoC::bind('\Gini\ORM\Taggible', function ($criteria) use ($db) {
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
                ->will($this->returnValue('taggible'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('taggible'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'object_type' => 'string',
                    'object_id' => 'string',
                    'object_field' => 'string',
                ]));
            $o->criteria = $criteria;
            return $o;
        });
    }

    public function tearDown(): void
    {
        \Gini\IoC::clear('\Gini\ORM\User');
        \Gini\IoC::clear('\Gini\ORM\Group');
        parent::tearDown();
    }

    public function testWhoAre()
    {
        \Gini\Those::reset();
        $those = those('user')
            ->whoAre('group.member')->of(
                those('group')->whose('name')->contains('f')->andWhose('name')->contains('g')
            );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0", "group" AS t1 LEFT JOIN "_group_member" AS "t2" ON "t2"."group_id"="t1"."id" LEFT JOIN "user" AS "t3" ON "t2"."member_id"="t3"."id" WHERE ("t1"."name" LIKE \'%f%\' AND "t1"."name" LIKE \'%g%\' AND "t0"."id" = "t3"."id")', $those->SQL());
    }

    public function testWhoAreWithWhose()
    {
        \Gini\Those::reset();
        $those = those('user')
            ->whose('father.name')->is('g')
            ->andWhoAre('group.member')->of(
                those('group')->whose('name')->contains('f')
            );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id", "group" AS t2 LEFT JOIN "_group_member" AS "t3" ON "t3"."group_id"="t2"."id" LEFT JOIN "user" AS "t4" ON "t3"."member_id"="t4"."id" WHERE "t1"."name"=\'g\' AND ("t2"."name" LIKE \'%f%\' AND "t0"."id" = "t4"."id")', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')
            ->whose('father.name')->is('g')
            ->whoAre('room/member.user')->of(
                those('room/member')->whose('room.name')->contains('f')
            );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" LEFT JOIN "user" AS "t1" ON "t0"."father_id"="t1"."id", "room_member" AS t2 LEFT JOIN "room" AS "t3" ON "t2"."room_id"="t3"."id" LEFT JOIN "user" AS "t4" ON "t2"."user_id"="t4"."id" WHERE "t1"."name"=\'g\' AND ("t3"."name" LIKE \'%f%\' AND "t0"."id" = "t4"."id")', $those->SQL());

        \Gini\Those::reset();
        $those = those('user')
            ->whoAre('object_id')->of(
                those('taggible')->whose('object_type')->is('user')->andWhose('object_field')->is('abc')
            );
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0", "taggible" AS t1 WHERE ("t1"."object_type"=\'user\' AND "t1"."object_field"=\'abc\' AND "t0"."id" = "t1"."object_id")', $those->SQL());
    }
}
