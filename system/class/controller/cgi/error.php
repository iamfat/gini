<?php

namespace GR\System\Controller {

	TRY_DECLARE('\Controller\Error', __FILE__);
	
	class Error extends \Controller\Layout {
	
		function __index($code = 404) {

			switch ($code) {
			case 401:
				$title = "Unauthorized visit";
				header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
				header("Status: 401 Unauthorized");
				break;
			case 404:
				$title = "File not found";
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
				header("Status: 404 Not Found");
				break;
			}
			
			$this->layout->title = $title;
			$this->layout->body = new \Model\View('error/'.$code);
		}
	
	}

}

namespace Controller {
	
	if (DECLARED('\Controller\Error', __FILE__)) {
		class Error extends \GR\System\Controller\Error {}
	}
}