<?php

namespace Gini\PHPUnit\Config;

class Config extends \Gini\PHPUnit\TestCase\CLI
{
    public function testNormalizeEnv()
    {
        $row = 'FOO="this\'s is value"';
        $normalizedRow = \Gini\Config::normalizeEnv($row);
        putenv($normalizedRow);
        self::assertEquals("this\'s is value", getenv('FOO'));

        $row = 'FOO=\'this\'s is value\'';
        $normalizedRow = \Gini\Config::normalizeEnv($row);
        putenv($normalizedRow);
        self::assertEquals("this\'s is value", getenv('FOO'));

        $row = 'FOO=this\\\'s is value';
        $normalizedRow = \Gini\Config::normalizeEnv($row);
        putenv($normalizedRow);
        self::assertEquals("this\'s is value", getenv('FOO'));

        $row = 'FOO2=some${FOO1:="HAHA"}';
        $normalizedRow = \Gini\Config::normalizeEnv($row);
        putenv($normalizedRow);
        self::assertEquals("someHAHA", getenv('FOO2'));

        $row = 'FOO2=some${FOO1:=\'HAHA\'}';
        $normalizedRow = \Gini\Config::normalizeEnv($row);
        putenv($normalizedRow);
        self::assertEquals("someHAHA", getenv('FOO2'));

        $row = 'FOO2=some${FOO1:=HA\\\'s HA}';
        $normalizedRow = \Gini\Config::normalizeEnv($row);
        putenv($normalizedRow);
        self::assertEquals("someHA\'s HA", getenv('FOO2'));
    }
}
