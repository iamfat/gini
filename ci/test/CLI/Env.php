<?php

namespace Gini\PHPUnit\CLI;

class Env extends \Gini\PHPUnit\TestCase\CLI
{
    public function testNormalizeEnv()
    {
        $row = 'FOO="this\'s is value"';
        $normalizedRow = \Gini\CLI\Env::normalize($row);
        putenv($normalizedRow);
        self::assertEquals("this\'s is value", getenv('FOO'));

        $row = 'FOO=\'this\'s is value\'';
        $normalizedRow = \Gini\CLI\Env::normalize($row);
        putenv($normalizedRow);
        self::assertEquals("this\'s is value", getenv('FOO'));

        $row = 'FOO=this\\\'s is value';
        $normalizedRow = \Gini\CLI\Env::normalize($row);
        putenv($normalizedRow);
        self::assertEquals("this\'s is value", getenv('FOO'));

        $row = 'FOO2=some${FOO1:="HAHA"}';
        $normalizedRow = \Gini\CLI\Env::normalize($row);
        putenv($normalizedRow);
        self::assertEquals("someHAHA", getenv('FOO2'));

        $row = 'FOO2=some${FOO1:=\'HAHA\'}';
        $normalizedRow = \Gini\CLI\Env::normalize($row);
        putenv($normalizedRow);
        self::assertEquals("someHAHA", getenv('FOO2'));

        $row = 'FOO2=some${FOO1:=HA\\\'s HA}';
        $normalizedRow = \Gini\CLI\Env::normalize($row);
        putenv($normalizedRow);
        self::assertEquals("someHA\'s HA", getenv('FOO2'));
    }

    public function testStripQuoteBranches(): void
    {
        // 双引号包裹
        self::assertEquals('hello world', \Gini\CLI\Env::stripQuote('"hello world"'));
        // 单引号包裹
        self::assertEquals('hello world', \Gini\CLI\Env::stripQuote("'hello world'"));
        // 引号不配对: 走 stripslashes 分支
        self::assertEquals("a'b", \Gini\CLI\Env::stripQuote("a\\'b"));
        self::assertEquals('', \Gini\CLI\Env::stripQuote(null));
        // 引号包裹的值原样返回（此处 PHP 单引号串中 \' 即 '）
        self::assertEquals("it's", \Gini\CLI\Env::stripQuote('"it\'s"'));
    }

    public function testNormalizeResolveEnvAndFallback(): void
    {
        // ${NAME} 无默认值且环境未设置 → 空字符串
        putenv('UT_ENV_UNSET=');
        $row = \Gini\CLI\Env::normalize('A=v[${UT_ENV_UNSET}]');
        putenv($row);
        self::assertEquals('v[]', getenv('A'));

        // ${NAME:=default} 取环境变量已有值（忽略默认值）
        putenv('UT_ENV_NAME=real');
        $row = \Gini\CLI\Env::normalize('B=${UT_ENV_NAME:=fallback}');
        putenv($row);
        self::assertEquals('real', getenv('B'));

        // 未加引号的普通值
        $row = \Gini\CLI\Env::normalize('C=plain');
        putenv($row);
        self::assertEquals('plain', getenv('C'));
    }

    public function testGetWithDefaultValue(): void
    {
        putenv('UT_ENV_EXIST=yes');
        self::assertEquals('yes', \Gini\CLI\Env::get('UT_ENV_EXIST', 'no'));
        // 未设置时使用默认值, 默认值同样过 stripQuote
        self::assertEquals('def', \Gini\CLI\Env::get('UT_ENV_MISSING_X', '"def"'));
    }
}
