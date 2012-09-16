<?php

namespace Test\Unit\System;

class Sample extends \Model\Test\Unit {

	function setup() {

	}

	function test_good() {
		$this->assert('good', 1);
	}

	function test_bad() {
		$this->assert('bad', 1);
	}

	function test_dependent() {
		$this->depend('good');

		$this->assert('dependent', 1);
	}

	function teardown() {

	}

}
