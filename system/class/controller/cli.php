<?php

namespace Controller;
	
abstract class CLI {

	static $CURRENT;

	function __index(&$args) {
		echo "\033[1;34mgini\033[0m: unknown command.\n";
	}

	function action_help(&$args) {
		echo "\033[1;34mgini\033[0m: help is unavailable.\n";
	}
		
}

