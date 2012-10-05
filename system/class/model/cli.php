<?php

namespace Model {
	
	final class CLI {

		const PARSE_BLANK = 0;
		const PARSE_IN_ARG = 1;
		const PARSE_IN_QUOTE = 2;

		static $buildin_commands = array(
			'help' => 'Help',
			'ls' => 'List available CLI apps',
			);

		static function on_readline_completion($input, $index) {
			$matches = array();

			$paths = \Gini\Core::phar_file_paths(CLASS_DIR, 'cli');
			foreach($paths as $path) {
				if (!is_dir($path)) continue;
				$dh = opendir($path);
				if ($dh) {
					while ($name = readdir($dh)) {
						if ($name[0] == '.') continue;
						if (!is_file($path . '/' . $name)) continue;
						$cli_name = basename($name, EXT);
						if (0 == strncmp($cli_name, $input, strlen($input))) {
							$matches[] = $cli_name; 
						}
					}
					closedir($dh);
				}

			}

			foreach(self::$buildin_commands as $c => $desc) {
				if (0 == strncmp($c, $input, strlen($input))) {
					$matches[] = $c; 
				}
			}

			return $matches;
		}

		static function parse_arguments($line) {
			$max = strlen($line);
			$st; 	// parsing status: PARSE_BLANK, PARSE_IN_ARG, PARSE_IN_QUOTE
			$qt;	// quote char
			$esc;	// escape or not
			$args = array();	// arguments

			for ($i = 0; $i < $max; $i++) {
				$c = $line[$i];
				if ($esc) {
					if ($c == '0' || $c == 'x') {
						$arg .= stripcslashes('\\'.substr($line, $i, 3));
						$i += 2;
					}
					else {
						$arg .= stripcslashes('\\'.$c);
						var_dump('\\'.$c);
					}

					$esc = FALSE;

					if ($st == self::PARSE_BLANK) {
						$st = self::PARSE_IN_ARG;
						$qt = NULL;							
					}
					continue;
				}
				elseif ($c == '\\') {
					$esc = TRUE;
					continue;
				}

				switch ($st) {
				case self::PARSE_BLANK:
					if ($c == ' ' || $c == "\t") {
						continue;
					}
					elseif ($c == '"' || $c == '\'') {
						$st = self::PARSE_IN_QUOTE;
						$qt = $c;
					}
					else {
						$arg .= $c;
						$st = self::PARSE_IN_ARG;
						$qt = NULL;
					}
					break;
				case self::PARSE_IN_ARG:
					if ($c == ' ' || $c == "\t") {
						$args[] = $arg;
						$arg = '';
						$st = self::PARSE_BLANK;
					}
					else {
						$arg .= $c;
					}
					break;
				case self::PARSE_IN_QUOTE:
					if ($c == $qt) {
						$st = self::PARSE_BLANK;
						$args[] = $arg;
						$arg = '';
					}
					else {
						$arg .= $c;
					}
					break;
				}

			}

			if ($arg) {
				$args[] = $arg;
			}

			return $args;
		}

		static function prompt_replace_cb($matches) {
			return $_SERVER[$matches[1]] ?: getenv($matches[1]) ?: $matches[0];
		}

		static function parse_prompt($prompt) {
			return preg_replace_callback('|%(\w+)|', '\Model\Shell::prompt_replace_cb', $prompt);
		}

		static function relaunch() {
			//$ph = proc_open($_SERVER['_'] . ' &', array(STDIN, STDOUT, STDERR), $pipes, NULL, $env);
			// fork process to avoid memory leak
			$env_path = '/tmp/gini-cli';
			$env_file = $env_path.'/'.posix_getpid().'.json';
			if (!file_exists($env_path)) @mkdir($env_path, 0777, true);
			file_put_contents($env_file, json_encode($_SERVER));
			if (isset($_SERVER['__RELAUNCH_PROCESS'])) {
				unset($_SERVER['__RELAUNCH_PROCESS']);
				exit(200);
			}
			else {
				do {
					// load $_SERVER from shared memo-cliry
					$_SERVER['__RELAUNCH_PROCESS'] = 1;
					$ph = proc_open($_SERVER['_'], array(STDIN, STDOUT, STDERR), $pipes, NULL, $_SERVER);
					if (is_resource($ph)) {
						$code = proc_close($ph);
						$_SERVER = (array) json_decode(@file_get_contents($env_file), TRUE);
					}					
				}
				while ($code == 200);
				exit;
			}
		}

		private static $prompt;

		static function main($argc, $argv) {

			if ($argc < 1) {
				static::command_help($argc, $argv);
				exit;
			}

			$cli = strtolower($argv[0]);
			$method = 'command_'.$cli;
			if (method_exists(__CLASS__, $method)) {
				call_user_func(array(__CLASS__, $method), $argc, $argv);
			}
			else {
				$GLOBALS['GINI.CURRENT_CLI'] = $cli;
				static::exec($argc, $argv);
			}

		}

		static function command_help($argc, $argv) {
			echo "usage: \033[1;34mgini\033[0m <command> [<args>]\n\n";
			echo "The most commonly used git commands are:\n";
			foreach(self::$buildin_commands as $k => $v) {
				printf("   \033[1;34m%-10s\033[0m %s\n", $k, $v);
			}
		}

		static function command_root() {
			echo $_SERVER['GINI_APP_PATH']."\n";
		}

		static function command_ls($argc, $argv) {
				// list available cli programs
				$paths = \Gini\Core::phar_file_paths(CLASS_DIR, 'cli');
				foreach($paths as $path) {
					$shortname = \Gini\Core::shortname($path);
					printf("\033[30;1;4m%s\033[0m:\n", $shortname);
					if (!is_dir($path)) continue;

					$dh = opendir($path);
					if ($dh) {
						while ($name = readdir($dh)) {
							if ($name[0] == '.') continue;
							if (!is_file($path . '/' . $name)) continue;
							printf("   %-10s ", basename($name, EXT));
						}
						echo "\n\n";
						closedir($dh);
					}

				}
		}

		static function exec($argc, $argv) {

			$cmd = $argv[0];
			if ($cmd[0] == '!') {
				$cmd = substr($cmd, 1);
				if (!$cmd) $cmd = 'bash';
				proc_close(proc_open($cmd, array(STDIN, STDOUT, STDERR), $pipes));
			}
			// @app: automatically set APP_PATH and run
			elseif ($cmd[0] == '@') {
				$app_base_path = realpath( isset($_SERVER['GINI_APP_BASE_PATH']) ? 
									$_SERVER['GINI_APP_BASE_PATH'] : $_SERVER['GINI_SYS_PATH'].'/..'
								 );

				$cmd = substr($cmd, 1);
				$_SERVER['GINI_APP_PATH'] = $app_base_path . '/' .$cmd;
				if (!is_dir($_SERVER['GINI_APP_PATH'] )) {
					exit("\033[1;34mgini\033[0m: missing app '$cmd'.\n");
				}

				array_shift($argv);
				$eargv = array(escapeshellcmd($_SERVER['_']));
				foreach ($argv as $arg) {
					$eargv[] = escapeshellcmd($arg);
				}
				proc_close(proc_open(implode(' ', $eargv), array(STDIN, STDOUT, STDERR), $pipes, NULL, $_SERVER));					
			}
			else {	
				// fork process to avoid memory leak
				$pid = pcntl_fork();
				if ($pid == -1) {
					exit("\033[1;34mgini\033[0m: cannot fork process\n");
				}
				elseif ($pid) {
					//parent process
					pcntl_wait($status);
				}
				else {
					$func = "\\CLI\\$cmd::main";
					if (is_callable($func)) {
						$GLOBALS['GINI.CURRENT_CLI'] = $cmd;
						call_user_func($func, count($argv), $argv);
					}
					else {
						exit("\033[1;34mgini\033[0m: '$cmd' is not a gini command. See 'gini help'.\n");
					}
				}
	
			}
		}

		static function shutdown() {
			if (isset($GLOBALS['GINI.CURRENT_CLI'])) {
				$cli = $GLOBALS['GINI.CURRENT_CLI'];
				$func = "\\CLI\\$cli::shutdown";
				if (is_callable($func)) {
					call_user_func($func);
				}
			}
		}

		static function exception($e) {
			if (isset($GLOBALS['GINI.CURRENT_CLI'])) {
				$cli = $GLOBALS['GINI.CURRENT_CLI'];

				$func = "\\CLI\\$cli::exception";
				if (is_callable($func)) {
					call_user_func($func, $e);
				}
				else {
					$message = $e->getMessage();
					$file = \Model\File::relative_path($e->getFile());
					$line = $e->getLine();
					fprintf(STDERR, "\033[314mERROR\033[0m \033[1m%s\033[0m (\033[34m%s\033[0m:$line)\n", $message, $file, $line);
					if (defined('DEBUG')) {
						$trace = array_slice($e->getTrace(), 1, 3);
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
			}
		}
	}

}

namespace CLI {
	
	abstract class Base {

		static function main($argc, $argv) {
		
			if ($argc < 2) {	
				exit("usage: \033[1;34mgini {$argv[0]}\033[0m <command> [<args>]\n");
			}
			
			$command = 'command_'.$argv[1];
			if (is_callable("static::$command")) {
				$argv = array_slice($argv, 1); 
				static::$command(count($argv), $argv);
			}
			else {
				exit("\033[1;34mgini {$argv[0]}\033[0m: unknown command '{$argv[1]}'. See 'gini {$argv[0]} help'.\n");
			}
			
		}

		static function command_help($argc, $argv) {
			echo "\033[1;34mgini {$argv[0]}\033[0m: help is unavailable.\n";
		}
			
	}

}
