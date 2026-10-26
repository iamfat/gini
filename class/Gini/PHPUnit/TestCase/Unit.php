<?php

namespace Gini\PHPUnit\TestCase;

use PHPUnit\Framework\TestCase;

/**
 * Base class for pure-logic tests: no CLI/CGI environment setup side effects.
 */
abstract class Unit extends TestCase
{
}
