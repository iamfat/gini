<?php

namespace Gini\PHPUnit\Core;

require_once __DIR__ . '/../gini.php';

class Event extends \Gini\PHPUnit\CLI {

    public function testBind()
    {
        \Gini\Event::bind('abc', function($e){
            return "foo";
        });
        
        $foo = \Gini\Event::trigger('abc');
        $this->assertEquals($foo, 'foo');
    }

}
