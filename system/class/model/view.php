<?php

namespace GR\System\Model {

	TRY_DECLARE('\Model\View', __FILE__);
	
	use \Gini\Event;
	use \Gini\Config;
	use \Gini\Core;

	class View {

		protected $_vars;
		protected $_path;

		static function setup() {}
		static function shutdown() {}
		
		function __construct($path, $vars=NULL){
			$this->_path = $path;
			$this->_vars = (array)$vars;
		}
			
		//返回子View
		function __get($key){
			assert($key[0] != '_');		
			return $this->_vars[$key];
		}

		function __set($key, $value) {
			assert($key[0] != '_');
			if ($value === NULL) {
				unset($this->_vars[$key]);
			} else {
				$this->_vars[$key] = $value;
			}
		}

		function __unset($key) {
			unset($this->_vars[$key]);
		}

		function __isset($key) {
			return isset($this->_vars[$key]);
		}
			
		private function __load_view($_path) {
			if ($_path) {
				ob_start();
				extract($this->_vars);

				@include($_path);

				$output = ob_get_contents();
				ob_end_clean();
			}
			
			return $output;
		}
		
		//返回View内容
		private $_ob_cache;		
		function __toString(){

			if ($this->_ob_cache !== NULL) return $this->_ob_cache;
			
			$path = $this->_path;
			$module = NULL;

			if ($path[0] == '!') {
				list($module, $path) = explode('/', $path, 2);
				$module = substr($module, 1);
			}

			$locale = Config::get('system.locale');
			$_path = Core::file_exists(VIEW_DIR.'@'.$locale.'/'.$path.VEXT, $module);
			if (!$_path) {
				$_path=Core::file_exists(VIEW_DIR.$path.VEXT, $module);
			}
			
			$output = $this->__load_view($_path);

			$event = $module ? "view[{$module}:{$path}].postrender ":'';
			$event .= "view[{$path}].postrender view.postrender";
			
			$new_output = (string) Event::trigger($event, $this, $output);

			$output = $new_output ?: (string) $output;

			return $this->_ob_cache = $output;
						
		}
		
		function set($name, $value=NULL){
			if(is_array($name)){
				array_map(array($this, __FUNCTION__), array_keys($name), array_values($name));
				return $this;
			} else {
				$this->$name=$value;
			}
			
			return $this;
		}
				
	}	
}

namespace Model {
	if (DECLARED('\Model\View', __FILE__)) {
		class View extends \GR\System\Model\View {}
	}
}

namespace {

	function V($path, $vars=NULL) {
		return new \Model\View($path, $vars);
	}

}
