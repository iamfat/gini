<?php

namespace Gini;

$GLOBALS['SCRIPT_START_AT'] = microtime(TRUE);

define('GINI_PATH', SYS_PATH.'class/gini/');

require GINI_PATH.'def.php';
require GINI_PATH.'core.php';

Core::setup();
Core::main();

// we do need to call shutdown here, since we've already register it to system shutdown event.
// Core::shutdown();
