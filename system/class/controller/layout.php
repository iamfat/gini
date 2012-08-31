<?php

namespace GR\System\Controller {

	TRY_DECLARE('\Controller\Layout', __FILE__);

	class Layout extends \Model\Controller {
		
		public $layout;
		protected $layout_name = 'layout';
		
		function __pre_action($method, &$params) {
			parent::__pre_action($method, $params);			
			
			$this->layout = new \Model\View($this->layout_name);			
		}
	
		function __post_action($method, &$params) {
			parent::__post_action($method, $params);	

			echo $this->layout;
		}
	
	}

}

namespace Controller {

	if (DECLARED('\Controller\Layout', __FILE__)) {
		class Layout extends \GR\System\Controller\Layout {}
	}
	
}