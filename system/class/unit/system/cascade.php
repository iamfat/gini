<?php

namespace Unit\System;

use \Gini\Config;
use \Gini\Core;

class Cascade extends \Model\Unit {

	function setup() {

	}

	function test_module() {

		$this->assert('defined APP_PATH', defined('APP_PATH'));
		$this->assert('APP_PATH == samples/hello', \Model\File::relative_path(APP_PATH) == 'samples/hello/');

	}

	function teardown() {

	}

}