<?php

define('CONFIG_DIR', 'config/');
define('CLASS_DIR', 'class/');
define('VIEW_DIR', 'view/');
define('DATA_DIR', 'data/');
define('I18N_DIR', 'i18n/');
define('THIRD_DIR', '3rd/');

define('CACHE_DIR', 'cache/');

define('EXT', '.php');
define('VEXT', '.phtml');

if (PHP_SAPI != 'cli') {

	if (extension_loaded('xcache')) {
		define('DEFAULT_CACHE', 'xcache');
	}
	elseif (extension_loaded('apc')) {
		define('DEFAULT_CACHE', 'apc');
	}

}

if (!defined('DEFAULT_CACHE')) {
	// means no cache...
	define('DEFAULT_CACHE', 'none');
}
