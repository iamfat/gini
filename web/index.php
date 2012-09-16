<?php

/*
ROOT_PATH=/usr/share/gini
APP_PATH=/var/lib/gini-apps/hello
DEBUG=0/1
*/

$sys_path = realpath(isset($_SERVER['GINI_SYS_PATH']) ? $_SERVER['GINI_SYS_PATH'] : (dirname(__FILE__).'/../system'));
$_SERVER['GINI_SYS_PATH'] = $sys_path;
define('SYS_PATH', $sys_path);

if (isset($_SERVER['GINI_DEBUG']) && $_SERVER['GINI_DEBUG']) {
	define('DEBUG', 1);
}

$class_path = SYS_PATH . '/class';
if (file_exists($class_path.'.phar')) {
	require 'phar://' . $class_path . '.phar/gini/bootstrap.php';
}
else {
	require $class_path . '/gini/bootstrap.php';
}

function exception($e) {
	$message = $e->getMessage();
	if ($message) {
		$file = \Model\File::relative_path($e->getFile());
		$line = $e->getLine();
		error_log(sprintf("\e[31m\e[4mERROR\e[0m \e[1m%s\e[0m", $message));
		$trace = array_slice($e->getTrace(), 1, 5);
		foreach ($trace as $n => $t) {
			error_log(sprintf("    %d) %s%s() in %s on line %d", $n + 1,
							$t['class'] ? $t['class'].'::':'', 
							$t['function'],
							\Model\File::relative_path($t['file']),
							$t['line']));

		}
	}

	if (PHP_SAPI != 'cli') {
		while(@ob_end_clean());	//清空之前的所有显示
		header('HTTP/1.1 500 Internal Server Error');
	}
}

function main($argc, $argv) {
	class_exists('Model\Controller');
	\Model\Controller\setup();
	\Model\Controller\main($argc, $argv);					// 分派控制器
}
