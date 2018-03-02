<?php

namespace Gini\PHPUnit\TestCase;

use \PHPUnit\Framework\TestCase;

abstract class CLI extends TestCase
{
    public static function setUpBeforeClass()
    {
        \Gini\CLI::setup();
    }

    public static function tearDownAfterClass()
    {
        \Gini\CLI::shutdown();
    }
}
