<?php

namespace Gini\PHPUnit\TestCase;

use PHPUnit\Framework\TestCase;

abstract class CLI extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        \Gini\CLI::setup();
    }

    public static function tearDownAfterClass(): void
    {
        \Gini\CLI::shutdown();
    }
}
