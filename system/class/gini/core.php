<?php

/**
 *
 * @package    Core
 * @author     黄嘉
 * @copyright  (c) 2009 基理科技
 */

namespace Gini {

	final class Core {

		static $PATH_INFO;
		static $PATH_TO_SHORTNAME;

		static function short_path($path) {
			$path_arr = explode('/', $path);
			$num = count($path_arr);
			for ($i=$num;$i>1;$i--) {
				$base = implode('/', array_slice($path_arr, 0, $i));
				if (isset(self::$PATH_TO_SHORTNAME[$base])) {
					$rpath = $i == $num ? '' : implode('/', array_slice($path_arr, $i));
					return self::$PATH_TO_SHORTNAME[$base] . '/' . $rpath;
				}
			}
		}

		static function shortname($path) {
			$path_arr = explode('/', $path);
			$num = count($path_arr);
			for ($i=$num;$i>1;$i--) {
				$base = implode('/', array_slice($path_arr, 0, $i));
				if (isset(self::$PATH_TO_SHORTNAME[$base])) {
					return self::$PATH_TO_SHORTNAME[$base];
				}
			}
		}

		static function fetch_info($path) {

			/*
			$shortname; $path;
			$name; $description; $version;
			$dependencies;
			*/

			$path = realpath($path);

			$info_script = $path.'/.gini';
			if (!file_exists($info_script)) return NULL;

			extract((array)@json_decode(@file_get_contents($info_script), TRUE));

			if (is_string($dependencies)) {
				$dependencies = explode(',', $dependencies);
			}

			$dependencies = (array) $dependencies;		
			foreach($dependencies as &$d) {
				$d = trim($d);
			}

			if (!$shortname) {
				$shortname = basename($path);
			}

			if ($shortname != 'system') {
				$dependencies[] = 'system';
			}

			return (object) compact('shortname', 'path', 'name', 'description', 'version', 'dependencies');
		}

		static function path_info($shortname) {
			return self::$PATH_INFO[$shortname] ?: NULL;
		}

		static function import($path) {
			$info = self::fetch_info($path);

			$inserted = FALSE;
			foreach ((array) self::$PATH_INFO as $b_shortname => $b_info) {

				if (!$inserted && in_array($info->shortname, $b_info->dependencies)) {
					$path_info[$info->shortname] = $info;
					$inserted = TRUE;
				}

				$path_info[$b_shortname] = $b_info;
			}

			if (!$inserted) {
				$path_info[$info->shortname] = $info;
			}

			self::$PATH_INFO = $path_info;
			self::$PATH_TO_SHORTNAME[$path] = $info->shortname;
		}

		static function autoload($class){
			//定义类后缀与类路径的对应关系
			$class = strtolower($class);
			$path = str_replace('\\', '/', $class);

			/*
			\GR\path\to\class
			*/

			list($gr, $class_path) = explode('/', $path, 2);
			if ($gr == 'gr') {

				for(;;) {

					list($s, $class_path) = explode('/', $class_path, 2);
					if (!$class_path) break;

					if ($scope) $scope .= '/' . $s;
					else $scope = $s;

					$file = Core::load(CLASS_DIR, $class_path, $scope);
					if (class_exists($class, FALSE)) break;

				}				

			}
			else {
				Core::load(CLASS_DIR, $path);
			}

		}

		static function load($base, $name, $scope=NULL) {
			if (is_array($base)) {
				foreach($base as $b){
					$file = Core::load($b, $name, $scope);
					if ($file) return $file;
				}
			}
			elseif (is_array($name)) {
				foreach($name as $n){
					$file = Core::load($base, $n, $scope);
					if ($file) return $file;
				}
			}
			else {
				$file = Core::phar_file_exists($base, $name.EXT, $scope);
				if ($file) {
 					require_once($file);
					return $file;
				}
			}
			return FALSE;
		}

		static function phar_file_exists($phar, $file, $scope=NULL) {

			if (is_null($scope)) {
				foreach (array_reverse(array_keys((array)self::$PATH_INFO)) as $scope) {
					$file_path = self::phar_file_exists($phar, $file, $scope);
					if ($file_path) return $file_path;
				}
			}
			elseif (isset(self::$PATH_INFO[$scope])) {
				$info = self::$PATH_INFO[$scope];
				if ($info->enabled === FALSE) {
					return FALSE;
				}

				$file_path = 'phar://'.$info->path . '/' . $phar . '.phar/' . $file;
				if (file_exists($file_path)) return $file_path;

				$file_path = $info->path . '/' . $phar . '/' . $file;
				if (file_exists($file_path)) return $file_path;

			}
			
			return NULL;		
		}

		static function file_exists($file, $scope = NULL) {

			if (is_null($scope)) foreach (array_reverse(array_keys((array)self::$PATH_INFO)) as $scope) {
				$file_path = self::file_exists($file, $scope);
				if ($file_path) return $file_path;
			}
			elseif (isset(self::$PATH_INFO[$scope])) {
				$info = self::$PATH_INFO[$scope];
				if ($info->enabled === FALSE) {
					return FALSE;
				}

				$file_path = $info->path . '/' . $file;
				if (file_exists($file_path)) return $file_path;

			}

			return NULL;

		}

		static function phar_file_paths($phar, $file) {

			foreach ((array) self::$PATH_INFO as $info) {
				if ($info->enabled === FALSE) {
					continue;
				}

				$file_path = 'phar://' . $info->path . '/' . $phar . '.phar/' . $file;
				if (file_exists($file_path)) {
					$file_paths[] = $file_path;
				}

				$file_path = $info->path . '/' . $phar . '/' . $file;
				if (file_exists($file_path)) {
					$file_paths[] = $file_path;
				}
			}

			return array_unique((array) $file_paths);
		}

		static function file_paths($file) {

			foreach ((array) self::$PATH_INFO as $info) {
				if ($info->enabled === FALSE) {
					continue;
				}

				$file_path = $info->path . '/' . $file;
				if (file_exists($file_path)) {
					$file_paths[] = $file_path;
				}
			}

			return array_unique((array) $file_paths);
		}

		static function exception($e) {
			if (function_exists('\\exception')) {
				\exception($e);
			}
			elseif (function_exists('exception')) {
				exception($e);
			}
			exit(1);
		}

		static function error($errno , $errstr, $errfile, $errline, $errcontext) {
			return Core::exception(new \ErrorException($errstr, $errno, 1, $errfile, $errline));
		}

		static function assertion($file, $line, $code) {
			return Core::exception(new \ErrorException($code, 0, 1, $file, $line));
		}

		static function setup(){

			error_reporting(E_ALL & ~E_NOTICE);

			spl_autoload_register('\Gini\Core::autoload');
			register_shutdown_function ('\Gini\Core::shutdown');
			set_exception_handler('\Gini\Core::exception');
			set_error_handler('\Gini\Core::error', E_ALL & ~E_NOTICE);

			assert_options(ASSERT_ACTIVE, 1);
			assert_options(ASSERT_WARNING, 0);
			assert_options(ASSERT_QUIET_EVAL, 1);
			assert_options(ASSERT_CALLBACK, '\Gini\Core::assertion');

			mb_internal_encoding('utf-8');
			mb_language('uni');

			self::import(SYS_PATH);

			// $_SERVER['GINI_APP_PATH'] = '/var/lib/gini-apps/hello'
			if (isset($_SERVER['GINI_APP_PATH'])) {
				$app_path = realpath($_SERVER['GINI_APP_PATH']);
				define('APP_PATH', $app_path);
				$_SERVER['GINI_APP_PATH'] = $app_path;
				self::import(APP_PATH);
			}
			else {
				define('APP_PATH', SYS_PATH);
			}

			\Application::setup();
		}

		static function main() {
			global $argc, $argv;
			\Application::main($argc, $argv);
		}

		static function shutdown() {

			\Application::shutdown();	
		}

	}

}

namespace {

	function TRACE() {
		$args = func_get_args();
		$fmt = array_shift($args);
		if (defined('DEBUG')) {
			if (PHP_SAPI == 'cli') {
				vfprintf(STDERR, "\e[36m\e[4mTRACE\e[0m $fmt\n", $args);
			}
			error_log(vsprintf("\e[36m\e[4mTRACE\e[0m $fmt", $args));			
		}
	}

	$_DECLARED;
	
	function TRY_DECLARE($name, $file) {
		global $_DECLARED;
		if (!isset($_DECLARED[$name])) {
			$_DECLARED[$name] = $file;
		}
	}

	function DECLARED($name, $file) {
		global $_DECLARED;
		return  $_DECLARED[$name] == $file;
	}

	function _G($key, $value = NULL) {
		if (is_null($value)) {
			return isset(\Gini\Core::$_G[$key]) ? \Gini\Core::$_G[$key] : NULL;
		}
		else {
			\Gini\Core::$_G[$key] = $value;
		}
	}

}