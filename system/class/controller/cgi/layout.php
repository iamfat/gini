<?php

namespace Controller\CGI {

	class Layout extends \Controller\CGI {
		
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
