<?php

namespace Gini\PHPUnit\Core;

class Event extends \Gini\PHPUnit\TestCase\CLI
{
    public function testBind()
    {
        \Gini\Event::bind('abc', function ($e) {
            return "foo";
        });

        $foo = \Gini\Event::trigger('abc');
        self::assertEquals($foo, 'foo');
    }

    public function testHandlersOrderedByWeightThenInsertion(): void
    {
        $name = 'ut-weight-' . uniqid();
        $log = [];
        \Gini\Event::bind($name, function () use (&$log) {
            $log[] = 'first';
        }, 10);
        \Gini\Event::bind($name, function () use (&$log) {
            $log[] = 'second';
        }, -10);
        \Gini\Event::bind($name, function () use (&$log) {
            $log[] = 'same-weight';
        }, 10);
        \Gini\Event::trigger($name);
        // uasort comparator 为 weight 小者排前; 同 weight 按插入 order
        self::assertSame(['second', 'first', 'same-weight'], $log);
    }

    public function testPassKeepsPreviousReturnValue(): void
    {
        $name = 'ut-pass-' . uniqid();
        \Gini\Event::bind($name, function ($e) {
            return 'first-return';
        });
        \Gini\Event::bind($name, function ($e) {
            $e->pass();

            return 'ignored-return';
        });
        self::assertSame('first-return', \Gini\Event::trigger($name));
    }

    public function testAbortStopsRemainingHandlers(): void
    {
        $name = 'ut-abort-' . uniqid();
        $log = [];
        \Gini\Event::bind($name, function ($e) use (&$log) {
            $log[] = 'before';
            $e->abort();

            return 'aborted';
        });
        \Gini\Event::bind($name, function ($e) use (&$log) {
            $log[] = 'after';
        });
        self::assertSame('aborted', \Gini\Event::trigger($name));
        self::assertSame(['before'], $log);
    }

    public function testTriggerChainsReturnAcrossEventsAndStopsOnAbort(): void
    {
        $first = 'ut-chain1-' . uniqid();
        $second = 'ut-chain2-' . uniqid();
        \Gini\Event::bind($first, function ($e) {
            return 'from-first';
        });
        \Gini\Event::bind($second, function ($e) {
            // 未设置 return 时沿用上一个事件的返回值
            $e->pass();
        });
        self::assertSame('from-first', \Gini\Event::trigger([$first, $second]));

        // 第一个事件 abort 后第二个事件不再触发
        $a = 'ut-abortchain1-' . uniqid();
        $b = 'ut-abortchain2-' . uniqid();
        $log = [];
        \Gini\Event::bind($a, function ($e) {
            $e->abort();

            return 'stop';
        });
        \Gini\Event::bind($b, function ($e) use (&$log) {
            $log[] = 'b-fired';
        });
        self::assertSame('stop', \Gini\Event::trigger([$a, $b]));
        self::assertSame([], $log);
    }

    public function testIsBindedWithStringAndArraySelectors(): void
    {
        $bound = 'ut-bind-' . uniqid();
        $unbound = 'ut-unbound-' . uniqid();
        \Gini\Event::bind($bound, function () {
        });
        self::assertTrue(\Gini\Event::isBinded($bound));
        self::assertFalse(\Gini\Event::isBinded($unbound));
        // 数组选择器: 任一已绑定即为 true
        self::assertTrue(\Gini\Event::isBinded([$unbound, $bound]));
        self::assertFalse(\Gini\Event::isBinded([$unbound, $unbound . '-2']));
    }

    public function testDuplicateClosureBindDedupedByKey(): void
    {
        $name = 'ut-dedupe-' . uniqid();
        $calls = 0;
        $handler = function () use (&$calls) {
            ++$calls;
        };
        \Gini\Event::bind($name, $handler);
        \Gini\Event::bind($name, $handler);
        \Gini\Event::trigger($name);
        self::assertSame(1, $calls);
    }

    public function testNonCallableCallbackStringYieldsNull(): void
    {
        $name = 'ut-noncallable-' . uniqid();
        \Gini\Event::bind($name, 'callback:Gini\\PHPUnit\\Core\\NonExistentHandler::missing');
        self::assertNull(\Gini\Event::trigger($name));
    }
}
