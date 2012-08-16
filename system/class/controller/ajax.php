<?php

namespace Controller;

abstract class _AJAX extends \Model\Controller {
	
	function _before_call($method, &$params){
		Event::bind('system.output', 'Output::AJAX');
		parent::_before_call($method, $params);
	}

}

if (class_exists('\Controller\AJAX', false)) {
	class AJAX extends _AJAX {};
}


