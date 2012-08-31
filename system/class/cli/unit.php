<?php

namespace CLI;

use \Gini\Core;

class Unit {
	
	static $stat;

	static function test($class) {
		
		if (class_exists($class)) {
			
			self::$stat['count'] ++;

			// fork process to avoid memory leak
			$pid = pcntl_fork();
			if ($pid == -1) {
				exit('cannot fork process');
			}
			elseif ($pid) {
				//parent process
				pcntl_wait($status);
				if ($status == 0) {
					self::$stat['pass'] ++;
				}
				else {
					self::$stat['fail'] ++;
				}
				return;
			}
			else {
				// run a single test
				$unit = new $class;
				$unit->test();
				exit($unit->passed() ? 0 : 1);
			}

		}

		// enumerate all files in the path for batch test
		// use Core::file_paths to support cascading file system
		$paths = Core::file_paths(CLASS_DIR.str_replace('\\', DIRECTORY_SEPARATOR, $class));
		foreach($paths as $path) {
			if (!is_dir($path)) continue;
			$dh = opendir($path);
			if ($dh) {
				while ($name = readdir($dh)) {
					if ($name[0] == '.') continue;
					self::test($class.'\\'.basename($name, '.php'));
				}
				closedir($dh);
			}

		}

	}

	static function main($argc, $argv) {

		if ($argc > 1) {
			array_shift($argv);
			foreach($argv as $arg) {
				$class = 'unit\\'.str_replace('/', '\\', strtolower($arg));
				self::test($class);
			}			
		}
		else {
			self::test('unit');
		}

		if (self::$stat['count'] > 0) {
			printf("\e[1mRESULTS\e[0m \n");
			printf("   \e[1m%d\e[0m tests performed!\n", self::$stat['count']);
			printf("   \e[1m%d\e[0m tests passed!\n", self::$stat['pass']);
			printf("   \e[1m%d\e[0m tests failed!\n", self::$stat['fail']);
		}
	}

	static function exception($e) {
		$message = $e->getMessage();
		fprintf(STDERR, "\e[31m\e[4mERROR\e[0m \e[1m%s\e[0m\n", $message);
		$trace = array_slice($e->getTrace(), 1, 8);
		foreach ($trace as $n => $t) {
			fprintf(STDERR, "%3d. %s%s() in %s on line %d\n", $n + 1,
							$t['class'] ? $t['class'].'::':'', 
							$t['function'],
							\Model\File::relative_path($t['file']),
							$t['line']);

		}

		fprintf(STDERR, "\n");
	}

}
