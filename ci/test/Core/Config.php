<?php

namespace Gini\PHPUnit\Core;

class Config extends \Gini\PHPUnit\TestCase\CLI
{
    public function testGet()
    {
        \Gini\Config::set('test', [
            'a' => [
                'b' => [
                    'c' => 'abc'
                ]
            ]
        ]);
        self::assertEquals(\Gini\Config::get('test.a.b.c'), 'abc');
    }
}
