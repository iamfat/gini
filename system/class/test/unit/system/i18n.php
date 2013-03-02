<?php

namespace Test\Unit\System {
	
	class I18N extends \Model\Test\Unit {

		function setup() {
			_CONF('system.locale', 'zh_CN');
			\Model\I18N::setup();
		}

		function test_domain() {
			// find ${DOMAIN_PATH} -iname "*.php"|xargs xgettext --keyword="T:1c,2" --from-code utf-8 -d${MODULE} -p ${DOMAIN_PATH}/i18n/
			$this->assert('T(\'Hello!\') => 你好!', T('Hello!') == '你好!');
		}


		function teardown() {

		}

	}	
}

	