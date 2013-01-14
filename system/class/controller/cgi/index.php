<?php

namespace Controller\CGI {

	class Index extends \Controller\CGI\Layout {
		
		function __index() {
			$this->layout->title = 'Gini PHP Framework';
			$this->layout->body = new \Model\View('body');
		}
		
	}
}

