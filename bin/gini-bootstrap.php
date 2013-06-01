<?php
/*
SYS_PATH=/usr/share/gini/system
APP_PATH=/var/lib/gini-apps/hello
I18N_PATH=/var/lib/gini-apps/hello/i18n
GN_DEBUG=0/1
*/

$sys_path = realpath(isset($_SERVER['GINI_SYS_PATH']) ? $_SERVER['GINI_SYS_PATH'] : (dirname(__FILE__).'/../system'));
$_SERVER['GINI_SYS_PATH'] = $sys_path;
define('SYS_PATH', $sys_path);

// locate GINI_APP_PATH and GINI_APP_BASE_PATH, contain info.php
if (!isset($_SERVER['GINI_APP_PATH'])) {
	$cwd = getcwd();
	$path_arr = explode('/', $cwd);
	$num = count($path_arr);
	for ($i=$num;$i>1;$i--) {
		$base = implode('/', array_slice($path_arr, 0, $i));
		if (file_exists($base . '/gini.json')) {
			$_SERVER['GINI_APP_PATH'] = $base;
			if (!isset($_SERVER['GINI_APP_BASE_PATH'])) {
				$_SERVER['GINI_APP_BASE_PATH'] = dirname($base);
			}
			break;
		}
	}
}

if (!isset($_SERVER['GINI_APP_PATH'])) {
	$_SERVER['GINI_APP_PATH'] = SYS_PATH;
}

// load class map
$class_map_file = $_SERVER['GINI_APP_PATH'].'/cache/class_map.json';
if (file_exists($class_map_file)) {
	$class_map = @json_decode(@file_get_contents($class_map_file), TRUE);
	if ($class_map) {
		$GLOBALS['gini.class_map'] = $class_map;
	}
}

// load view map
$view_map_file = $_SERVER['GINI_APP_PATH'].'/cache/view_map.json';
if (file_exists($view_map_file)) {
	$view_map = @json_decode(@file_get_contents($view_map_file), TRUE);
	if ($view_map) {
		$GLOBALS['gini.view_map'] = $view_map;
	}
}

$class_path = SYS_PATH . '/class';
if (file_exists($class_path.'.phar')) {
	require 'phar://' . $class_path . '.phar/gini/bootstrap.php';
}
else {
	require $class_path . '/gini/bootstrap.php';
}

