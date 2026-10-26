<?php

namespace Gini\PHPUnit\ORM;

class ORMIterator extends \Gini\PHPUnit\TestCase\CLI
{
    use \Gini\PHPUnit\MockDatabase;

    private $resultRows = [];
    private $queries = [];
    private $countSQL = null;
    private $totalCountValue = 0;

    public function setUp(): void
    {
        parent::setUp();

        $db = $this->createDatabaseMock(['query', 'quote', 'quoteIdent', 'ident', 'value']);

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

        $this->attachQueryHandler($db, $result, function ($SQL, $idents, $params) {
            $this->queries[] = [$SQL, $idents, $params];
        });

        $db->expects($this->any())
            ->method('value')
            ->will($this->returnCallback(function ($SQL) {
                $this->countSQL = $SQL;

                return $this->totalCountValue;
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

    public function testFetch()
    {
        \Gini\Those::reset();
        $this->resultRows = [
            ['id' => 20, 'name' => 'a'],
            ['id' => 30, 'name' => 'b'],
            ['id' => 40, 'name' => 'c'],
        ];
        $users = \Gini\IoC::construct('\Gini\ORMIterator', 'user')->query('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"=100');

        $criterias = [];
        foreach ($users as $u) {
            $criterias[] = $u->criteria();
        }

        self::assertEquals($criterias, [['id' => 20], ['id' => 30], ['id' => 40]]);
    }

    public function testValidBeyondEnd()
    {
        \Gini\Those::reset();
        $this->resultRows = [
            ['id' => 20, 'name' => 'a'],
        ];
        $users = \Gini\IoC::construct('\Gini\ORMIterator', 'user')->query('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"=100');

        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno & (E_DEPRECATED | E_USER_DEPRECATED)) {
                $deprecations[] = $errstr;

                return true;
            }

            return false;
        });

        try {
            $seen = 0;
            foreach ($users as $u) {
                ++$seen;
            }

            // once exhausted, further valid() calls stay false and silent
            $validAfterEnd = $users->valid();
            $validAfterEndAgain = $users->valid();
        } finally {
            restore_error_handler();
        }

        self::assertSame(1, $seen);
        self::assertFalse($validAfterEnd);
        self::assertFalse($validAfterEndAgain);
        self::assertSame([], $deprecations, 'valid() past the end must not trigger a null-offset deprecation');
    }

    private function paginatedIterator(string $limitSQL = ''): \Gini\ORMIterator
    {
        \Gini\Those::reset();
        $base = 'SELECT SQL_CALC_FOUND_ROWS "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE "t0"."money"=100';

        return \Gini\IoC::construct('\Gini\ORMIterator', 'user')->query($base . $limitSQL);
    }

    public function testCountReflectsFetchedRows(): void
    {
        $this->resultRows = [
            ['id' => 20, 'name' => 'a'],
            ['id' => 30, 'name' => 'b'],
            ['id' => 40, 'name' => 'c'],
        ];
        $users = $this->paginatedIterator();

        self::assertSame(3, $users->count());
        self::assertSame([20, 30, 40], $users->keys());
    }

    public function testOffsetSetAdoptsObjectIdAndTracksTouch(): void
    {
        \Gini\Those::reset();
        $users = \Gini\IoC::construct('\Gini\ORMIterator', 'user');

        $user = a('user', 7);
        $users[null] = $user;

        self::assertSame([7], $users->keys());
        self::assertSame(1, $users->count());
        self::assertTrue($users->isTouched());

        $users->resetTouch();
        self::assertFalse($users->isTouched());

        unset($users[7]);
        self::assertSame(0, $users->count());
        self::assertFalse(isset($users[7]));
    }

    public function testTotalCountDerivesCountSQLWithoutPaging(): void
    {
        $this->resultRows = [];
        $this->totalCountValue = 137;
        $users = $this->paginatedIterator(' ORDER BY "t0"."id" ASC LIMIT 10,20');

        self::assertSame(137, $users->totalCount());
        // 现状：count_SQL 正则仅在 COUNT(t.*) 形态时收敛为 "id"；
        // DISTINCT 多列查询被整体包进 COUNT(...)，钉扎该真实输出
        self::assertStringStartsWith('SELECT COUNT( "t0"."id","t0"."_extra","t0"."name"', $this->countSQL);
        self::assertStringContainsString(') AS "count" FROM "user" AS "t0" WHERE "t0"."money"=100', $this->countSQL);
        self::assertStringNotContainsString('SQL_CALC_FOUND_ROWS', $this->countSQL);
        self::assertStringNotContainsString('ORDER BY', $this->countSQL);
        self::assertStringNotContainsString('LIMIT', $this->countSQL);
    }

    public function testPaginationKeepsPageRowsAndTotalCountApart(): void
    {
        $this->resultRows = [
            ['id' => 20, 'name' => 'a'],
            ['id' => 30, 'name' => 'b'],
        ];
        $this->totalCountValue = 95;
        $users = $this->paginatedIterator(' ORDER BY "t0"."id" LIMIT 0,2');

        // 先触发数据 fetch, 再校验数据 SQL 原样携带 LIMIT
        self::assertSame(2, $users->count());
        $dataSQL = end($this->queries)[0];
        self::assertStringContainsString('LIMIT 0,2', $dataSQL);
        self::assertSame(95, $users->totalCount());
        self::assertStringNotContainsString('LIMIT', $this->countSQL);
    }

    public function testSetFetchFlagCachesAndWildcardClears(): void
    {
        $it = new \Gini\PHPUnit\ORM\FetchFlagIterator('user');

        self::assertFalse($it->exposedIsFlagged('data'));

        $it->exposedSetFlag('data');
        self::assertTrue($it->exposedIsFlagged('data'));

        $it->exposedSetFlag('data', false);
        self::assertFalse($it->exposedIsFlagged('data'));

        $it->exposedSetFlag('dryrun');
        self::assertTrue($it->exposedIsFlagged('dryrun'));

        $it->exposedSetFlag('*', false);
        self::assertFalse($it->exposedIsFlagged('dryrun'));

        $it->exposedSetFlag('*');
        self::assertTrue($it->exposedIsFlagged('anything'));
    }
}

class FetchFlagIterator extends \Gini\ORMIterator
{
    public function exposedSetFlag($scope, $enable = true)
    {
        return $this->setFetchFlag($scope, $enable);
    }

    public function exposedIsFlagged($scope)
    {
        return $this->isFetchFlagged($scope);
    }
}
