<?php

namespace Controller;

abstract class CGI {

	// current controller
	static $CURRENT;

	function __pre_action($action, &$params) { }
	
	function __post_action($action, &$params) { }

}
