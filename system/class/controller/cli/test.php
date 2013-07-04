<?php

namespace Controller\CLI {

    class Test extends \Controller\CLI {

        private $stat;

        private function _run($class) {

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
                        $this->_run($class.'\\'.basename($name, '.php'));
                    }
                    closedir($dh);
                }

            }

        }

        function action_help(&$argv) {
            exit("Usage: \x1b[1;34mgini test\x1b[0m [unit/integration/performance/fixtures]/path/to/test\n");            
        }

        function __index(&$argv) {
            if (count($argv) < 1) {
                return $this->help($argv);
            }

            foreach($argv as $arg) {
                $class = 'test\\'.str_replace('/', '\\', strtolower($arg));
                $this->_run($class);
            }            

            if ($this->stat['count'] > 0) {
                printf("\x1b[1mRESULTS\x1b[0m \n");
                printf("   \x1b[1m%d\x1b[0m tests performed!\n", $this->stat['count']);
                printf("   \x1b[1m%d\x1b[0m tests passed!\n", $this->stat['pass']);
                printf("   \x1b[1m%d\x1b[0m tests failed!\n", $this->stat['fail']);
            }
        }
        
    }

}