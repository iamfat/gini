<?php

namespace Model {
	
	class CLI {

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