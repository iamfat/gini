<?php

namespace Gini\PHPUnit\Core;

class IoC extends \Gini\PHPUnit\TestCase\CLI
{
    public function testBind()
    {
        $a = \Gini\IoC::construct('stdClass');
        $this->assertFalse(isset($a->foo));

        \Gini\IoC::bind('stdClass', function () {
            return (object) ['foo' => 'bar', 'uniqid' => uniqid()];
        });

        $a = \Gini\IoC::construct('stdClass');
        $this->assertEquals($a->foo, 'bar');

        $b = \Gini\IoC::construct('stdClass');
        $this->assertNotEquals($a->uniqid, $b->uniqid);

        \Gini\IoC::singleton('stdClass', function () {
            return (object) ['foo' => 'bar', 'uniqid' => uniqid()];
        });

        $a = \Gini\IoC::construct('stdClass');
        $this->assertEquals($a->foo, 'bar');

        $b = \Gini\IoC::construct('stdClass');
        $this->assertEquals($b->foo, 'bar');

        $this->assertEquals($a->uniqid, $b->uniqid);

        \Gini\IoC::instance('stdClass', $a);
        $c = \Gini\IoC::construct('stdClass');
        $this->assertEquals($c->foo, 'bar');
        $this->assertEquals($a->uniqid, $c->uniqid);

        \Gini\IoC::clear('stdClass');
        $a = \Gini\IoC::construct('stdClass');
        $this->assertFalse(isset($a->foo));
    }
}
