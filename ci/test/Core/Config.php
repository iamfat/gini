<?php

namespace Gini\PHPUnit\Core;

class Config extends \Gini\PHPUnit\TestCase\CLI
{
    private $configBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBackup = \Gini\Config::export();
    }

    protected function tearDown(): void
    {
        // clear()/import() 用例清空全局配置, 必须还原避免污染后续测试
        \Gini\Config::import($this->configBackup);
        parent::tearDown();
    }

    public function testGet()
    {
        \Gini\Config::set('test', [
            'a' => [
                'b' => [
                    'c' => 'abc'
                ]
            ]
        ]);
        self::assertEquals(\Gini\Config::get('test.a.b.c'), 'abc');
    }

    public function testGetMissReturnsNull(): void
    {
        self::assertNull(\Gini\Config::get('ut.missing.path'));
    }

    public function testGetScalarInterruptReturnsNull(): void
    {
        \Gini\Config::set('ut.scalar', ['leaf' => 'x']);
        // 点路径深入到标量后继续取值: 命中 scalar 提前 break 返回 null
        self::assertNull(\Gini\Config::get('ut.scalar.leaf.deeper'));
    }

    public function testSetDottedAndTopLevelAndUnset(): void
    {
        // set 只按第一个点切分: category=ut, 键名 dotted
        \Gini\Config::set('ut.dotted', 'v1');
        self::assertSame('v1', \Gini\Config::get('ut.dotted'));

        \Gini\Config::set('uttop', ['x' => 1]);
        self::assertSame(['x' => 1], \Gini\Config::get('uttop'));

        // val === null: 点路径 unset category 下的单个键
        \Gini\Config::set('ut.dotted', null);
        self::assertNull(\Gini\Config::get('ut.dotted'));

        // 顶层 category 整体 unset
        \Gini\Config::set('uttop', null);
        self::assertNull(\Gini\Config::get('uttop'));
    }

    public function testAppendBranches(): void
    {
        \Gini\Config::clear();
        // 原值不存在(null) → 直接赋值
        \Gini\Config::append('ut.new', 'first');
        self::assertSame('first', \Gini\Config::get('ut.new'));

        // 原值是数组 → 追加
        \Gini\Config::set('ut.arr', ['a']);
        \Gini\Config::append('ut.arr', 'b');
        self::assertSame(['a', 'b'], \Gini\Config::get('ut.arr'));

        // 原值是标量 → 字符串拼接
        \Gini\Config::append('ut.new', '-second');
        self::assertSame('first-second', \Gini\Config::get('ut.new'));
    }

    public function testImportExportClear(): void
    {
        \Gini\Config::import(['ut' => ['k' => 1]]);
        self::assertSame(['ut' => ['k' => 1]], \Gini\Config::export());
        \Gini\Config::clear();
        self::assertSame([], \Gini\Config::export());
    }

    public function testGetNestedPath(): void
    {
        \Gini\Config::set('ut.deep', ['a' => ['b' => ['c' => 42]]]);
        self::assertSame(42, \Gini\Config::get('ut.deep.a.b.c'));
    }
}
