<?php

namespace Model {
	
	final class Shell {

		const PARSE_BLANK = 0;
		const PARSE_IN_ARG = 1;
		const PARSE_IN_QUOTE = 2;

		static $buildin_commands = array(
			'help' => 'Help',
			'exit' => 'Exit shell',
			'ls' => 'List available CLI apps',
			'clear' => 'Clear screen',
			'env' => 'Print environment variables',
			'setenv' => 'Set environment variables',
			'history' => 'Read shell history (libreadline required)',
			'prompt' => 'Set current prompt',
			);

		static function on_readline_completion($input, $index) {
			$matches = array();

			$paths = \Gini\Core::file_paths(CLASS_DIR.'cli');
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
			if (isset($_SERVER['FORKED'])) {
				exit(200);
			}
			else {
				$_SERVER['FORKED'] = 1;
				do {
					$ph = proc_open($_SERVER['_'], array(STDIN, STDOUT, STDERR), $pipes, NULL, $_SERVER);
					if (is_resource($ph)) {
						$code = proc_close($ph);
					}					
				}
				while ($code == 200);
				exit;
			}
		}

		static function main() {

			$_SERVER['PID'] = posix_getpid();

			$prompt = "[%PID]\e[34;1mGINI\e[0m@";

			readline_completion_function('\Model\Shell::on_readline_completion');

			for(;;) {

				$line = readline(static::parse_prompt($prompt.' '));
				if (!$line) continue;
				readline_add_history($line);

				$args = static::parse_arguments($line);

				switch ($args[0]) {
				case 'help':
				case '?':
					foreach(self::$buildin_commands as $k => $v) {
						printf("%-10s %s\n", $k, $v);
					}
					break;
				case 'exit':
				case 'quit':
					exit;
				case 'clear':
					echo "\e[2J\e[H";
					break;
				case 'history':
					if (function_exists('readline_list_history')) {
						array_map(readline_list_history(), 'echo');
					}
					else {
						echo "history command is not supported!\n";
					}
					break;
				case 'setenv':
					// 设置环境变量
					if (count($args) > 2) {
						$_SERVER[$args[1]] = $args[2];
						static::relaunch();
					}
					else {
						echo "Usage: setenv KEY VALUE\n";
					}
					break;
				case 'env':
					// 获得环境变量
					$results = array();
					if (count($args) == 1) {
						foreach($_SERVER as $k => $v) {
							$results[$k] = $k;	
						}
					}
					else {
						foreach($_SERVER as $k => $v) {
							if (fnmatch($args[1], $k)) {
								$results[$k] = $k;
							}
						}
					}

					unset($results['argc']);
					unset($results['argv']);

					foreach($results as $k) {
						$v = $_SERVER[$k];
						printf("%s = \"\e[31m%s\e[0m\"\n", $k, addcslashes((string) $v, "\\\'\"\n\r"));
					}

					break;
				case 'ls':
					// list available cli programs
					$paths = \Gini\Core::file_paths(CLASS_DIR.'cli');
					foreach($paths as $path) {
						if (!is_dir($path)) continue;
						$dh = opendir($path);
						if ($dh) {
							while ($name = readdir($dh)) {
								if ($name[0] == '.') continue;
								if (!is_file($path . '/' . $name)) continue;
								echo basename($name, EXT) . "\t";
							}
							closedir($dh);
						}

					}
					echo "\n";
					break;
				case 'prompt':
					if (count($args) > 1) {
						$prompt = $args[1];
					}
					else {
						echo preg_replace('/\e(\[[\d;]+[a-z])/i', "\e$1\e[30m*$1\e[0m\e$1", $prompt)."\n";
					}
					break;
				default:
					$cmd = $args[0];
					if ($cmd[0] == '!') {
						$cmd = substr($cmd, 1);
						if (!$cmd) $cmd = 'bash';
						$ph = proc_open($cmd, array(STDIN, STDOUT, STDERR), $pipes);
					}
					else {
						$ph = proc_open($_SERVER['_'] . ' ' . $line, array(STDIN, STDOUT, STDERR), $pipes, NULL, $env);
					}
					if (is_resource($ph)) {
						proc_close($ph);
					}
				}
			}

		}

	}

}
