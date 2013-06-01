<?php

namespace Controller;
	
abstract class CLI {

	function __index(&$args) {
		echo "\x1b[1;34mgini\x1b[0m: unknown command.\n";
	}

	function action_help(&$args) {
		echo "\x1b[1;34mgini\x1b[0m: help is unavailable.\n";
	}
		
}

