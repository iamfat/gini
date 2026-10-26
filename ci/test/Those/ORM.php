<?php

namespace Gini\PHPUnit\Those;

class ORM extends \Gini\PHPUnit\TestCase\CLI
{
    use \Gini\PHPUnit\MockDatabase;

    private $resultRows = [];
    private $SQLs = [];

    public function setUp(): void
    {
        parent::setUp();

        $db = $this->createDatabaseMock(['query', 'quote', 'quoteIdent', 'ident']);

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

                return $r ?? [];
            }));

        $this->attachQueryHandler($db, $result, function ($SQL) {
            $this->SQLs[] = $SQL;
        });

        $this->bindIoC('\Gini\ORM\User', function ($criteria = null) use ($db) {
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
                    'prop' => 'bigint,many',
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
        $this->clearMockIoC();
        parent::tearDown();
    }

    public function testThoseMethods()
    {
        \Gini\Those::reset();
        $user = a('user')->whose('money')->is(100);
        $user->those()->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"=100', $user->those()->SQL());

        \Gini\Those::reset();
        $user = a('user')->whose('name')->beginsWith('COOL');
        $user->those()->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'COOL%\'', $user->those()->SQL());

        \Gini\Those::reset();
        $user = a('user')->whose('name')->contains('COOL');
        $user->those()->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."name" LIKE \'%COOL%\'', $user->those()->SQL());
    }

    public function testFetch()
    {
        \Gini\Those::reset();
        $user = a('user')->whose('money')->is(100);
        $this->resultRows = [
            ['id' => 1],
        ];
        $user->fetch();
        self::assertEquals(
            'SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"=100 LIMIT 1',
            $this->SQLs[0]
        );
    }
}
