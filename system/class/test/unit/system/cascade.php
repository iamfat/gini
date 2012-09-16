<?php

namespace Test\Unit\System;

use \Model\Config;
use \Gini\Core;

class Cascade extends \Model\Test\Unit {

	function setup() {

	}

	function test_module() {

		$this->assert('defined APP_PATH', defined('APP_PATH'));
		$this->assert('APP_PATH == samples/hello', \Model\File::relative_path(APP_PATH) == 'samples/hello/');

	}

	function teardown() {

	}

}