<?php

namespace Unit\System;

use \Gini\Config;

class I18N extends \Model\Unit {

	function setup() {
		Config::set('system.locale', 'en_US');
		\Model\I18N::setup();
	}

	function test_domain() {
		// find ${DOMAIN_PATH} -iname "*.php"|xargs xgettext --keyword="T:1c,2" --from-code utf-8 -d${MODULE} -p ${DOMAIN_PATH}/i18n/
		$this->assert('你好! => Hello!', T('你好!') == 'Hello!');
	}


	function teardown() {
		\Model\I18N::shutdown();
	}

}