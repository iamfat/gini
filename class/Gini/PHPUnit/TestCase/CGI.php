<?php

namespace Gini\PHPUnit\TestCase;

use PHPUnit\Framework\TestCase;

abstract class CGI extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        \Gini\CGI::setup();
    }

    public static function tearDownAfterClass(): void
    {
        \Gini\CGI::shutdown();
    }
}
