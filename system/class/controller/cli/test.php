<?php

namespace Controller\CLI {

	class Test extends \Controller\CLI {

		private $stat;

		private function run($class) {

			if (class_exists($class)) {
				
				$this->stat['count'] ++;

				// fork process to avoid memory leak
				$pid = pcntl_fork();
				if ($pid == -1) {
					exit('cannot fork process');
				}
				elseif ($pid) {
					//parent process
					pcntl_wait($status);
					if ($status == 0) {
						$this->stat['pass'] ++;
					}
					else {
						$this->stat['fail'] ++;
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
						$this->run($class.'\\'.basename($name, '.php'));
					}
					closedir($dh);
				}

			}

		}

		function help($argv) {
			exit("Usage: \033[1;34mgini test\033[0m [unit/integration/performance/fixtures]/path/to/test\n");			
		}

		function __index($argv) {
			if (count($argv) < 1) {
				return $this->help($argv);
			}

			foreach($argv as $arg) {
				$class = 'test\\'.str_replace('/', '\\', strtolower($arg));
				$this->run($class);
			}			

			if ($this->stat['count'] > 0) {
				printf("\033[1mRESULTS\033[0m \n");
				printf("   \033[1m%d\033[0m tests performed!\n", $this->stat['count']);
				printf("   \033[1m%d\033[0m tests passed!\n", $this->stat['pass']);
				printf("   \033[1m%d\033[0m tests failed!\n", $this->stat['fail']);
			}
		}
		
	}

}