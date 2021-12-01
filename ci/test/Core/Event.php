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
}
