<?php

namespace Gini\PHPUnit\Core;

require_once __DIR__.'/../gini.php';

class Util extends \Gini\PHPUnit\CLI
{
    public function testGetOpt()
    {
        $opt = \Gini\Util::getOpt(['bar', '--prefix=hello', 'foo', '--suffix'], '', ['prefix:', 'suffix:']);
        $this->assertEquals($opt['prefix'], 'hello');
        $this->assertFalse($opt['suffix']);
    }

}

