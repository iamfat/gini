<?php

namespace Gini\PHPUnit\Core;

class Util extends \Gini\PHPUnit\TestCase\CLI
{
    public function testGetOpt()
    {
        $opt = \Gini\Util::getOpt(['bar', '--prefix=hello', 'foo', '--suffix'], '', ['prefix:', 'suffix:']);
        $this->assertEquals($opt['prefix'], 'hello');
        $this->assertFalse($opt['suffix']);
    }

}

