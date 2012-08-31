<?php

/*
ROOT_PATH=/usr/share/gini
APP_PATH=/var/lib/gini-apps/hello
GN_DEBUG=0/1
*/

$root_path = realpath(isset($_SERVER['ROOT_PATH']) ? $_SERVER['ROOT_PATH'] : (dirname(__FILE__).'/..'));

$phar_path = $root_path.'system.phar';
if (is_file($phar_path)) {
	define('SYS_PATH', 'phar://'.$root_path.'/system.phar/');
}
else {
	define('SYS_PATH', $root_path.'/system/');
}

if (isset($_SERVER['GN_DEBUG']) && $_SERVER['GN_DEBUG']) {
	define('DEBUG', 1);
}

require SYS_PATH.'class/gini/bootstrap.php';

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

function setup() {
	class_exists('Model\Controller');
	\Model\Controller\setup();
}

function main($argc, $argv) {
	\Model\Controller\main($argc, $argv);					// 分派控制器
}
