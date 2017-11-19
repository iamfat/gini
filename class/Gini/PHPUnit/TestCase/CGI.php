<?php

namespace Gini\PHPUnit\TestCase;

use \PHPUnit\Framework\TestCase;

abstract class CGI extends TestCase
{
    public static function setUpBeforeClass()
    {
        \Gini\CGI::setup();
    }

    public static function tearDownAfterClass()
    {
        \Gini\CGI::shutdown();
    }
}
