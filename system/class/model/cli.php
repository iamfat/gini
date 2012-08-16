<?php

namespace Model {
	
	class CLI {

		static function main($argc, $argv) {
		
			if ($argc < 2) {	
				exit("Usage: {$argv[0]} command [args...]\n");
			}
			
			$command = 'command_'.$argv[1];
			if (is_callable("static::$command")) {
				$argv = array_slice($argv, 2); 
				static::$command(count($argv), $argv);
			}
			
		}
			
	}

}