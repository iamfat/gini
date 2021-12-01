<?php

namespace Gini\PHPUnit\Core;

class IoC extends \Gini\PHPUnit\TestCase\CLI
{
    public function testBind()
    {
        $a = \Gini\IoC::construct('stdClass');
        self::assertFalse(isset($a->foo));

        \Gini\IoC::bind('stdClass', function () {
            return (object) ['foo' => 'bar', 'uniqid' => uniqid()];
        });

        $a = \Gini\IoC::construct('stdClass');
        self::assertEquals($a->foo, 'bar');

        $b = \Gini\IoC::construct('stdClass');
        self::assertNotEquals($a->uniqid, $b->uniqid);

        \Gini\IoC::singleton('stdClass', function () {
            return (object) ['foo' => 'bar', 'uniqid' => uniqid()];
        });

        $a = \Gini\IoC::construct('stdClass');
        self::assertEquals($a->foo, 'bar');

        $b = \Gini\IoC::construct('stdClass');
        self::assertEquals($b->foo, 'bar');

        self::assertEquals($a->uniqid, $b->uniqid);

        \Gini\IoC::instance('stdClass', $a);
        $c = \Gini\IoC::construct('stdClass');
        self::assertEquals($c->foo, 'bar');
        self::assertEquals($a->uniqid, $c->uniqid);

        \Gini\IoC::clear('stdClass');
        $a = \Gini\IoC::construct('stdClass');
        self::assertFalse(isset($a->foo));
    }
}
