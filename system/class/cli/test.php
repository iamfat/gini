<?php

namespace CLI {

	class Test {

		static $stat;
		static function run($class) {
			
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
					$test = new $class;
					$test->run();
					exit($test->passed() ? 0 : 1);
				}

			}

			// enumerate all files in the path for batch test
			// use Core::file_paths to support cascading file system
			$paths = \Gini\Core::phar_file_paths(CLASS_DIR, str_replace('\\', '/', $class));
			foreach($paths as $path) {
				if (!is_dir($path)) continue;
				$dh = opendir($path);
				if ($dh) {
					while ($name = readdir($dh)) {
						if ($name[0] == '.') continue;
						self::run($class.'\\'.basename($name, '.php'));
					}
					closedir($dh);
				}

			}

		}

		static function main($argc, $argv) {
			if ($argc <= 1 || $argv[1] == 'help') {
				exit("Usage: \e[1;34mgini test\e[0m [unit/integration/performance/fixtures]/path/to/test\n");
			}

			array_shift($argv);
			foreach($argv as $arg) {
				$class = 'test\\'.str_replace('/', '\\', strtolower($arg));
				self::run($class);
			}			

			if (self::$stat['count'] > 0) {
				printf("\e[1mRESULTS\e[0m \n");
				printf("   \e[1m%d\e[0m tests performed!\n", self::$stat['count']);
				printf("   \e[1m%d\e[0m tests passed!\n", self::$stat['pass']);
				printf("   \e[1m%d\e[0m tests failed!\n", self::$stat['fail']);
			}
		}
		
	}

}