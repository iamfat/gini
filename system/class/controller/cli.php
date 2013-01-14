<?php

namespace Controller {
	
	abstract class CLI {

		static $CURRENT;

		function __index($argv) {
			echo "\033[1;34mgini\033[0m: unknown command.\n";
		}

		function help($argv) {
			echo "\033[1;34mgini\033[0m: help is unavailable.\n";
		}
			
	}

}
