<?php

namespace GR\System\Controller {

	TRY_DECLARE('\Controller\Index', __FILE__);
	
	class Index extends \Controller\Layout {
		
		function __index() {
			$this->layout->title = 'Gini PHP Framework';
			$this->layout->body = new \Model\View('body');
		}
		
	}
}

namespace Controller {

	if (DECLARED('\Controller\Index', __FILE__)) {
		class Index extends \GR\System\Controller\Index {}
	}

}