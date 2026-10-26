<?php

// Test-only bootstrap: production bootstrap first, then eagerly load ORM/Those
// so their trailing global namespace blocks (a()/an()/those()/whose()/anyOf()/
// allOf()/whoAre()) are always defined regardless of test execution order.
// lib/bootstrap.php itself MUST stay untouched (production entry point).

require __DIR__ . '/../lib/bootstrap.php';

require_once SYS_PATH . '/class/Gini/ORM.php';
require_once SYS_PATH . '/class/Gini/Those.php';

// Same reason as ORM/Those: the global function T() lives in I18N.php's
// trailing `namespace { }` block, so it only exists once the class file is
// autoloaded. Tests using \Gini\PHPUnit\TestCase\Unit never trigger that
// autoload, hence preload it here to keep every test independently runnable.
require_once SYS_PATH . '/class/Gini/I18N.php';
