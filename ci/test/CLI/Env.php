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
}
