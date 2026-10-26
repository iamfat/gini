<?php

namespace Gini\PHPUnit\Core;

class I18N extends \Gini\PHPUnit\TestCase\Unit
{
    public function testStripContextReturnsTranslationAfterSeparator(): void
    {
        // \004 (EOT) 之前是 gettext context, 之后是 msgid
        self::assertSame('Submit', \Gini\I18N::stripContext("button\x04Submit"));
    }

    public function testStripContextReturnsWholeStringWithoutSeparator(): void
    {
        self::assertSame('plain string', \Gini\I18N::stripContext('plain string'));
    }

    public function testStripContextWithEmptyTranslationFallsBackToContext(): void
    {
        // $t 为空字符串时回落到 context 部分
        self::assertSame('context', \Gini\I18N::stripContext("context\x04"));
    }

    public function testTFallsBackToStrippedMessageWhenNoTranslation(): void
    {
        // gettext 无翻译目录时返回 msgid 本身, T() 去掉 context 前缀
        self::assertSame('Hello', T("greeting\x04Hello"));
        self::assertSame('Hi', T('Hi'));
        // falsy msgid 原样返回
        self::assertSame('', T(''));
    }

    public function testTInterpolatesParams(): void
    {
        self::assertSame('Hello, world!', T('Hello, {name}!', ['{name}' => 'world']));
    }
}
