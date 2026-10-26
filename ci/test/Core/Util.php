<?php

namespace Gini\PHPUnit\Core;

class Util extends \Gini\PHPUnit\TestCase\CLI
{
    public function testGetOpt()
    {
        $opt = \Gini\Util::getOpt(['bar', '--prefix=hello', 'foo', '--suffix'], '', ['prefix:', 'suffix:']);
        self::assertEquals($opt['prefix'], 'hello');
        self::assertFalse($opt['suffix']);
    }

    public function testSingularize()
    {
        self::assertEquals(\Gini\Util::singularize('some/birds'), 'some/bird');
        self::assertEquals(\Gini\Util::singularize('some/data'), 'some/datum');
    }

    public function testArrayMergeDeepAppendsNumericKeys(): void
    {
        $merged = \Gini\Util::arrayMergeDeep(['a'], ['b', 'c']);
        self::assertSame(['a', 'b', 'c'], $merged);
    }

    public function testArrayMergeDeepRecursesStringKeys(): void
    {
        $merged = \Gini\Util::arrayMergeDeep(
            ['k' => ['a' => 1, 'nested' => ['x' => 1]]],
            ['k' => ['b' => 2, 'nested' => ['y' => 2]]]
        );
        self::assertSame(['k' => ['a' => 1, 'nested' => ['x' => 1, 'y' => 2], 'b' => 2]], $merged);
    }

    public function testArrayMergeDeepReplacesScalarTargetWithArray(): void
    {
        // 目标键存在但是标量、来源是数组: 标量被重置为数组后递归合并
        $merged = \Gini\Util::arrayMergeDeep(['k' => 'scalar'], ['k' => ['x' => 1]]);
        self::assertSame(['k' => ['x' => 1]], $merged);

        // 来源标量直接覆盖
        $merged = \Gini\Util::arrayMergeDeep(['k' => ['x' => 1]], ['k' => 'scalar']);
        self::assertSame(['k' => 'scalar'], $merged);
    }

    public function testArrayReplaceKeys(): void
    {
        $arr = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = \Gini\Util::arrayReplaceKeys($arr, ['a' => 'alpha', 'c' => 'gamma']);
        self::assertSame(['alpha' => 1, 'b' => 2, 'gamma' => 3], $result);
        // 引用传入, 原变量同时被改写
        self::assertSame($result, $arr);
    }

    public function testPathAndArgsStopsAtInvalidToken(): void
    {
        // 命中首个不符合 ^[a-z][a-z0-9-_]+$ 的参数后停止, 剩余 argv 作为候选值
        $candidates = \Gini\Util::pathAndArgs(['foo', 'bar', '1bad', 'x']);
        self::assertSame([
            '/foo' => ['bar', '1bad', 'x'],
            '/foo/bar' => ['1bad', 'x'],
        ], $candidates);

        // 首参即不合法（单字符也不满足量词 +）→ 空候选
        self::assertSame([], \Gini\Util::pathAndArgs(['a']));
    }

    public function testPathAndArgsGuessCase(): void
    {
        $candidates = \Gini\Util::pathAndArgs(['foo_bar', 'baz-qux'], true);
        self::assertSame([
            '/FooBar' => ['baz-qux'],
            '/FooBar/BazQux' => [],
        ], $candidates);
    }

    public function testHyphenateAndCache(): void
    {
        self::assertSame('-foo-bar', \Gini\Util::hyphenate('FooBar'));
        self::assertSame('abc', \Gini\Util::hyphenate('abc'));
        // UPPERCASE_PATTERN 无 u 修饰符: 多字节大写不匹配, 'ÄÖ' 原样返回
        self::assertSame('ÄÖ', \Gini\Util::hyphenate('ÄÖ'));
        // 结果缓存为静态属性
        self::assertSame('-foo-bar', \Gini\Util::$hyphenateCache['FooBar']);
        self::assertSame(\Gini\Util::hyphenate('FooBar'), \Gini\Util::$hyphenateCache['FooBar']);
    }

    public function testPluralize(): void
    {
        self::assertEquals('birds', \Gini\Util::pluralize('bird'));
        self::assertEquals('some/data', \Gini\Util::pluralize('some/datum'));
    }

    /**
     * @dataProvider passwordLevelProvider
     */
    public function testRandPassword(int $length, int $level, string $charset): void
    {
        $password = \Gini\Util::randPassword($length, $level);
        self::assertSame($length, strlen($password));
        for ($i = 0; $i < $length; ++$i) {
            self::assertNotFalse(strpos($charset, $password[$i]), "char {$password[$i]} not allowed in level $level");
        }
    }

    public function passwordLevelProvider(): array
    {
        return [
            [12, 1, '0123456789abcdfghjkmnpqrstvwxyz'],
            [16, 2, '0123456789abcdfghjkmnpqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'],
            [20, 3, '0123456789_!@#$%&*()-=+/abcdfghjkmnpqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_!@#$%&*()-=+/'],
        ];
    }

    public function testParseArgs(): void
    {
        self::assertSame(['foo', 'bar', 'baz'], \Gini\Util::parseArgs('foo bar baz'));
    }
}
