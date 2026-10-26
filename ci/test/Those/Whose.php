<?php

namespace Gini\PHPUnit\Those;

class Whose extends \Gini\PHPUnit\TestCase\CLI
{
    use \Gini\PHPUnit\MockDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $db = $this->createDatabaseMock();

        $this->bindIoC('\Gini\ORM\User', function ($criteria = null) use ($db) {
            $o = self::getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'ownProperties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects(self::any())->method('db')->will(self::returnValue($db));
            $o->expects(self::any())->method('name')->will(self::returnValue('user'));
            $o->expects(self::any())->method('tableName')->will(self::returnValue('user'));
            $o->expects(self::any())
                ->method('ownProperties')
                ->will(self::returnValue([
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

    private function sqlFor(callable $build): string
    {
        \Gini\Those::reset();
        $those = those('user');
        $build($those);
        $those->makeSQL();

        return $those->SQL();
    }

    public function testLikeOperators(): void
    {
        $head = 'SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."money","t0"."father_id","t0"."description" FROM "user" AS "t0" WHERE ';

        self::assertSame(
            $head . '"t0"."name" LIKE \'COOL%\'',
            $this->sqlFor(function ($t) {
                $t->whose('name')->beginsWith('COOL');
            })
        );
        // beginsWith 是 startsWith 的别名
        self::assertSame(
            $head . '"t0"."name" LIKE \'COOL%\'',
            $this->sqlFor(function ($t) {
                $t->whose('name')->startsWith('COOL');
            })
        );
        self::assertSame(
            $head . '"t0"."name" LIKE \'%COOL%\'',
            $this->sqlFor(function ($t) {
                $t->whose('name')->contains('COOL');
            })
        );
        self::assertSame(
            $head . '"t0"."name" LIKE \'%COOL\'',
            $this->sqlFor(function ($t) {
                $t->whose('name')->endsWith('COOL');
            })
        );
        self::assertSame(
            $head . '"t0"."name" NOT LIKE \'COOL\'',
            $this->sqlFor(function ($t) {
                $t->whose('name')->notLike('COOL');
            })
        );
    }

    public function testInAndNotInScalarLists(): void
    {
        self::assertStringContainsString(
            'WHERE "t0"."money" IN (1, 2, 3)',
            $this->sqlFor(function ($t) {
                $t->whose('money')->isIn([1, 2, 3]);
            })
        );
        self::assertStringContainsString(
            'WHERE "t0"."money" NOT IN (1, 2)',
            $this->sqlFor(function ($t) {
                $t->whose('money')->isNotIn([1, 2]);
            })
        );
        // 空 IN 列表退化为 1 = 0
        self::assertStringContainsString(
            'WHERE 1 = 0',
            $this->sqlFor(function ($t) {
                $t->whose('money')->isIn([]);
            })
        );
    }

    public function testNullComparisons(): void
    {
        // 普通可空标量列 is(null) 不产生 join, 直接 IS NULL
        self::assertStringContainsString(
            'WHERE "t0"."description" IS NULL',
            $this->sqlFor(function ($t) {
                $t->whose('description')->is(null);
            })
        );
        self::assertStringContainsString(
            'WHERE "t0"."description" IS NOT NULL',
            $this->sqlFor(function ($t) {
                $t->whose('description')->isNot(null);
            })
        );
    }

    public function testFieldReferenceValueResolvesToIdent(): void
    {
        // @field 语法把右侧解析为同表字段标识符, 不加引号
        self::assertStringContainsString(
            'WHERE "t0"."money"="t0"."father_id"',
            $this->sqlFor(function ($t) {
                $t->whose('money')->is('@father_id');
            })
        );
    }

    public function testAndOrComposition(): void
    {
        $sql = $this->sqlFor(function ($t) {
            $t->whose('money')->is(100)
                ->andWhose('name')->is('A')
                ->orWhose('name')->is('B');
        });
        self::assertStringContainsString(
            'WHERE "t0"."money"=100 AND "t0"."name"=\'A\' OR "t0"."name"=\'B\'',
            $sql
        );
    }

    public function testConditionRecordsOpAndParams(): void
    {
        // 直接断言 Condition DTO 的 op/params 归一化
        $c = new \Gini\Those\Whose('name');
        self::assertSame($c, $c->match('^=', 'abc'));
        self::assertSame('like', $this->readProperty($c, 'op'));
        self::assertSame('abc%', $this->readProperty($c, 'params'));

        $c = new \Gini\Those\Whose('name');
        $c->match('$=', 'abc');
        self::assertSame('%abc', $this->readProperty($c, 'params'));

        $c = new \Gini\Those\Whose('name');
        $c->match('*=', 'abc');
        self::assertSame('%abc%', $this->readProperty($c, 'params'));

        $c = new \Gini\Those\Whose('f');
        $c->match('!=', 7);
        self::assertSame('<>', $this->readProperty($c, 'op'));

        $c = new \Gini\Those\Whose('f');
        $c->match('==', 7);
        self::assertSame('=', $this->readProperty($c, 'op'));

        // 未知操作符原样透传
        $c = new \Gini\Those\Whose('f');
        $c->match('<<=', 7);
        self::assertSame('<<=', $this->readProperty($c, 'op'));
        self::assertSame(7, $this->readProperty($c, 'params'));

        // isIn/isNotIn/isRelatedTo/isBetween 设置 DTO 状态
        $c = new \Gini\Those\Whose('f');
        $c->isIn([1, 2]);
        self::assertSame('in', $this->readProperty($c, 'op'));
        self::assertSame([1, 2], $this->readProperty($c, 'params'));

        $c = new \Gini\Those\Whose('f');
        $c->isNotIn([1]);
        self::assertSame('not in', $this->readProperty($c, 'op'));

        $c = new \Gini\Those\Whose('f');
        $c->isRelatedTo('kw');
        self::assertSame('match', $this->readProperty($c, 'op'));
        self::assertSame('kw', $this->readProperty($c, 'params'));

        $c = new \Gini\Those\Whose('f');
        $c->isBetween(1, 2);
        self::assertSame('between', $this->readProperty($c, 'op'));
        self::assertSame([1, 2], $this->readProperty($c, 'params'));
    }

    private function readProperty($object, string $name)
    {
        $r = new \ReflectionProperty(\Gini\Those\Whose::class, $name);
        $r->setAccessible(true);

        return $r->getValue($object);
    }
}
