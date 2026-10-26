<?php

namespace Gini\PHPUnit\Helper;

class TouchableArray extends \Gini\PHPUnit\TestCase\Unit
{
    public function testInitiallyNotTouched(): void
    {
        $arr = new \Gini\Helper\TouchableArray(['a' => 1]);
        self::assertFalse($arr->isTouched());
        self::assertSame(1, $arr['a']);
    }

    public function testOffsetSetMarksTouched(): void
    {
        $arr = new \Gini\Helper\TouchableArray();
        $arr['a'] = 1;
        self::assertTrue($arr->isTouched());
        self::assertSame(1, $arr['a']);

        // 覆盖已有键同样标记
        $arr->resetTouch();
        self::assertFalse($arr->isTouched());
        $arr['a'] = 2;
        self::assertTrue($arr->isTouched());
    }

    public function testAppendMarksTouched(): void
    {
        $arr = new \Gini\Helper\TouchableArray();
        $arr[] = 'v';
        self::assertTrue($arr->isTouched());
        self::assertSame('v', $arr[0]);
    }

    public function testOffsetUnsetMarksTouched(): void
    {
        $arr = new \Gini\Helper\TouchableArray(['a' => 1]);
        $arr->resetTouch();
        unset($arr['a']);
        self::assertTrue($arr->isTouched());
        self::assertFalse(isset($arr['a']));
    }

    public function testResetTouchClearsDirtyFlag(): void
    {
        $arr = new \Gini\Helper\TouchableArray(['a' => 1]);
        $arr['a'] = 2;
        $arr->resetTouch();
        self::assertFalse($arr->isTouched());
        self::assertSame(2, $arr['a']);
    }
}
