<?php

namespace Controller;

abstract class CGI {

	function __pre_action($action, &$params) { }
	
	function __post_action($action, &$params, $response) { }

	function action() {
		list($action,$params)=func_get_args();
		
		$action = $action ?: '__index';
		$params = (array) $params;

		$this->__pre_action($action, $params);
		$response = call_user_func_array(array($this, $action), $params);
		$response = $this->__post_action($action, $params, $response) ?: $response;

		if ($response) $response->output();
	}

}
