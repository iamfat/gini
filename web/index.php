<?php

require '.bootstrap.php';

use \Model\Cache;
use \Model\Config;
use \Model\I18N;
use \Model\CGI;
use \Model\Log;

class Application {

	static function setup() {
		Cache::setup();
		Config::setup();
		I18N::setup();
		Log::setup();
		CGI::setup();
	}

	static function main($argv) {			
		CGI::main($argv);					// 分派控制器
	}

	static function shutdown() {
		return CGI::shutdown();
	}

	static function exception($e) {
		return CGI::exception($e);
	}

}