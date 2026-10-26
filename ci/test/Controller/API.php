<?php

namespace Gini\PHPUnit\Controller;

class API extends \Gini\PHPUnit\TestCase\CLI
{
    public function testPropertiesDeclared()
    {
        $o = new class extends \Gini\Controller\API {
        };

        self::assertTrue(property_exists($o, 'app'), 'Controller\\API must declare $app');
        self::assertTrue(property_exists($o, 'env'), 'Controller\\API must declare $env');

        $o->app = (object) ['id' => 'gini'];
        $o->env = ['foo' => 'bar'];
        self::assertEquals((object) ['id' => 'gini'], $o->app);
        self::assertEquals(['foo' => 'bar'], $o->env);
    }
}
