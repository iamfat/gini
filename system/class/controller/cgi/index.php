<?php

namespace Controller\CGI {

	class Index extends Layout {
		
		function __index() {
			$this->view->title = 'Gini PHP Framework';
			$this->view->body = V('phtml/body');
		}
		
	}
}

