<?php

namespace Gini;

$GLOBALS['SCRIPT_START_AT'] = microtime(true);

require __DIR__.'/def.php';
require __DIR__.'/core.php';

Core::setup();
Core::main();

// we do need to call shutdown here, since we've already register it to system shutdown event.
// Core::shutdown();
