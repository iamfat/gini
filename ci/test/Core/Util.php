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
}
