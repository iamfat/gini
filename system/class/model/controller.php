<?php

namespace GR\System\Model {

	TRY_DECLARE('\Model\Controller', __FILE__);

	class Controller {

		// current controller
		static $CURRENT;

		function __pre_action($method, &$params) { }
		
		function __post_action($method, &$params) { }

	}

}

namespace Model {

	if (DECLARED('\Model\Controller', __FILE__)) {
		class Controller extends \GR\System\Model\Controller {}
	}

}

namespace Model\Controller {

	use \Gini\Core;
	use \Model\Config;

	use \Model\Input;
	use \Model\URI;


	function setup() {
		global $argc, $argv;
		$argv = Input::args();
		$argc = count($argv);
	}

	$dispatchers = array();
	function set_dispatcher($mime, $dispatcher) {
		$dispatchers[$mime] = $dispatcher;
	}

	function main($argc, $argv) {
		$accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
		while (NULL !== ($accept = array_pop($accepts))) {
			list($mime,) = explode(';', $accept, 2);
			$dispatcher = $dispatchers[$mime];
			if ($dispatcher) {
				return call_user_func($dispatcher, $argc, $argv);
			}
		}

		return default_dispatcher($argc, $argv);	
	}

	function default_dispatcher($argc, $argv) {
		
		//从末端开始尝试
		/*
			home/page/edit/1/2

			home/page/index.php Controller\Page\Index::edit(1)
			home/page/index.php Controller\Index::index('edit', 1)
			home/page.php		Controller\Page::edit(1)
			home/page.php		Controller\Page::index('edit', 1)
		*/

		$args = (array) $argv;
		$path = '';
		$candidates = array('index' => $args);
		while (count($args) > 0) {
			$arg = array_shift($args);
			if (!preg_match('|^[a-z]\w+$|', $arg)) break;
			if ($path) $path .= DIRECTORY_SEPARATOR;
			$path .= $arg;
			$candidates[$path] = $args;
		} 

		$class = NULL;
		foreach(array_reverse($candidates) as $path => $params){
			$basename = basename($path);
			$dirname = dirname($path);
			$class_namespace = '\\Controller\\';
			if ($dirname != '.') {
				$class_namespace .= str_replace('/', '_', ucwords($dirname)).'\\';
			}
			$class = $class_namespace . ucwords($basename);
			if (class_exists($class)) break;
			$class = $class_namespace . 'Controller_' . ucwords($basename);
			if (class_exists($class)) break;
		}

		if (!$class || !class_exists($class, FALSE)) URI::redirect('error/404');

		_CONF('runtime.controller_path', $path);
		_CONF('runtime.controller_class', $class);

		$controller = new $class;

		$action = $params[0];
		if($action && $action[0]!='_' && method_exists($controller, $action)){
			array_shift($params);
		} 
		elseif ($action && $action[0]!='_' && method_exists($controller, 'do_'.$action)) {
			$action = 'do_'.$action;
			array_shift($params);
		}
		elseif (method_exists($controller, '__index')) {
			$action = '__index';
		}
		else {
			URI::redirect('error/404');
		}

		\Model\Controller::$CURRENT = $controller;
		_CONF('runtime.controller_action', $action);
		_CONF('runtime.controller_params', $params);

		$controller->__pre_action($action, $params);
		call_user_func_array(array($controller, $action), $params);
		$controller->__post_action($action, $params);
		
	}

}