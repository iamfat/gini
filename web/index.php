<?php

require '.bootstrap.php';

class Application {

	static function setup() {
		\Model\Cache::setup();
		\Model\Config::setup();
		\Model\I18N::setup();
		\Model\Input::setup();
		\Model\Output::setup();
		\Model\CGI::setup();
	}

	static function main($argv) {			
		\Model\CGI::main($argv);					// 分派控制器
	}

	static function shutdown() {
		return \Model\CGI::shutdown();
	}

	static function exception($e) {
		return \Model\CGI::exception($e);
	}

}