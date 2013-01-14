<?php

namespace Controller {

	abstract class CGI {

		// current controller
		static $CURRENT;

		function __pre_action($method, &$params) { }
		
		function __post_action($method, &$params) { }

	}

}