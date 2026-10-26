<?php

namespace Gini\PHPUnit\Those;

class SQLHelper extends \Gini\PHPUnit\TestCase\CLI
{
    use \Gini\PHPUnit\MockDatabase;

    private $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->createDatabaseMock();

        $this->bindIoC('\Gini\ORM\User', function () {
            $o = self::getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects(self::any())->method('db')->will(self::returnValue($this->db));
            $o->expects(self::any())->method('name')->will(self::returnValue('user'));
            $o->expects(self::any())->method('tableName')->will(self::returnValue('user'));

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

    private function helper(): \Gini\Those\SQLHelper
    {
        \Gini\Those\SQLHelper::reset();

        return new \Gini\Those\SQLHelper('user');
    }

    public function testUniqidAliasesAndReset(): void
    {
        \Gini\Those\SQLHelper::reset();
        $h1 = new \Gini\Those\SQLHelper('user');
        self::assertSame('t0', $h1->tableAlias());
        self::assertSame('user', $h1->table());
        self::assertSame($this->db, $h1->db());

        $h2 = new \Gini\Those\SQLHelper('user');
        self::assertSame('t1', $h2->tableAlias());

        \Gini\Those\SQLHelper::reset();
        self::assertSame('t0', (new \Gini\Those\SQLHelper('user'))->tableAlias());
    }

    public function testPluralNameResolvesViaConfig(): void
    {
        \Gini\Those\SQLHelper::reset();
        $h = new \Gini\Those\SQLHelper('users');
        self::assertSame('user', $h->table());
    }

    public function testFragmentWithAndChains(): void
    {
        $h = $this->helper();
        $h->whose('money')->is(100);
        $h->whose('name')->beginsWith('A');
        self::assertSame(
            '"t0"."money"=100 AND "t0"."name" LIKE \'A%\'',
            $h->fragment()
        );
    }

    public function testOrWhoseChainsWithOr(): void
    {
        $h = $this->helper();
        $h->whose('money')->is(100)
            ->orWhose('name')->is('B');
        self::assertSame(
            '"t0"."money"=100 OR "t0"."name"=\'B\'',
            $h->fragment()
        );
    }

    public function testWhoIsStartsFreshClauseWithoutAnd(): void
    {
        $h = $this->helper();
        $h->whose('money')->is(100);
        $h->whoIs('name')->is('A');
        // whoIs 不向 _where 压 AND, 但前一条已收尾 => 片段直接相邻拼接
        self::assertSame(
            '"t0"."money"=100 "t0"."name"=\'A\'',
            $h->fragment()
        );
    }

    public function testInScalarRendersInList(): void
    {
        $h = $this->helper();
        $h->whose('money')->isIn(1, 2, 3);
        self::assertSame('"t0"."money" IN (1, 2, 3)', $h->fragment());

        $h = $this->helper();
        $h->whose('money')->isNotIn(1, 2);
        self::assertSame('"t0"."money" NOT IN (1, 2)', $h->fragment());
    }

    public function testBetweenAndNullAndMatchOperators(): void
    {
        $h = $this->helper();
        $h->whose('money')->isBetween(1, 2);
        self::assertSame('("t0"."money">=1 AND "t0"."money"<2)', $h->fragment());

        $h = $this->helper();
        $h->whose('name')->is(null);
        self::assertSame('"t0"."name" IS NULL', $h->fragment());

        $h = $this->helper();
        $h->whose('name')->isNot(null);
        self::assertSame('"t0"."name" IS NOT NULL', $h->fragment());

        $h = $this->helper();
        $h->whose('name')->isRelatedTo('keyword');
        self::assertSame(
            'MATCH("t0"."name") AGAINST (\'keyword\')',
            $h->fragment()
        );
    }

    public function testAtReferenceResolvesAliasedField(): void
    {
        $h = $this->helper();
        $h->alias('u');
        $h->whose('money')->is('@u.father_id');
        self::assertSame('"t0"."money"="t0"."father_id"', $h->fragment());
    }

    public function testWhereReplacesConditionArray(): void
    {
        $h = $this->helper();
        $h->where(['"t0"."a"=1', '"t0"."b"=2']);
        self::assertSame('("t0"."a"=1) AND ("t0"."b"=2)', $h->fragment());

        $h = $this->helper();
        $h->where(['"t0"."a"=1']);
        self::assertSame('"t0"."a"=1', $h->fragment());
    }

    public function testLimitFormatting(): void
    {
        $h = $this->helper();
        $h->limit(10, 20);
        self::assertStringContainsString(' LIMIT 10, 20', $h->finalize());

        $h = $this->helper();
        $h->limit(5);
        self::assertStringContainsString(' LIMIT 5', $h->finalize());

        // per_page <= 0 时只渲染单值
        $h = $this->helper();
        $h->limit(5, 0);
        self::assertStringContainsString(' LIMIT 5', $h->finalize());
    }

    public function testOrderByModes(): void
    {
        foreach (['desc', 'd'] as $mode) {
            $h = $this->helper();
            $h->orderBy('money', $mode);
            self::assertStringContainsString('ORDER BY "t0"."money" DESC', $h->finalize());
        }
        foreach (['asc', 'a'] as $mode) {
            $h = $this->helper();
            $h->orderBy('money', $mode);
            self::assertStringContainsString('ORDER BY "t0"."money" ASC', $h->finalize());
        }

        // 未识别的 mode 不产生排序
        $h = $this->helper();
        $h->orderBy('money', 'sideways');
        self::assertStringNotContainsString('ORDER BY', $h->finalize());
    }

    public function testFinalizeAssemblesFullSelectAndConsumesState(): void
    {
        $h = $this->helper();
        $h->whose('money')->is(100);
        $h->orderBy('money');
        $h->limit(0, 10);
        // 注意：SQLHelper::finalize 的 select 列恒为 id；
        // order_by 字段键是数字下标, += 被已有键 0 跳过（Those 子类才把排序列并入 select）
        self::assertSame(
            'SELECT DISTINCT "t0"."id" FROM "user" AS "t0" WHERE "t0"."money"=100 ORDER BY "t0"."money" ASC LIMIT 0, 10',
            $h->finalize()
        );

        // finalize/fragment 会清空 where/join/order/limit（一次性消费）
        self::assertSame(
            'SELECT DISTINCT "t0"."id" FROM "user" AS "t0"',
            $h->finalize()
        );
    }
}
