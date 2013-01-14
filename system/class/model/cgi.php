<?php

namespace Model {

	use \Gini\Core;
	use \Model\Config;

	use \Model\Input;
	use \Model\URI;

	class CGI {

		static function stripslashes(& $value)
		{
			return is_array($value) ?
					array_map(array(__CLASS__, __FUNCTION__), $value) :
					stripslashes($value);
		}
	
		static $dispatchers = array();
		static function set_dispatcher($mime, $dispatcher) {
			$dispatchers[$mime] = $dispatcher;
		}

		static function main($argc, $argv) {
			$accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
			while (NULL !== ($accept = array_pop($accepts))) {
				list($mime,) = explode(';', $accept, 2);
				$dispatcher = $dispatchers[$mime];
				if ($dispatcher) {
					return call_user_func($dispatcher, $argc, $argv);
				}
			}

			return self::default_dispatcher($argc, $argv);	
		}

		static function default_dispatcher($argc, $argv) {
			
			//从末端开始尝试
			/*
				home/page/edit/1/2

				home/page/index.php Controller\Page\Index::edit(1)
				home/page/index.php Controller\Index::index('edit', 1)
				home/page.php		Controller\Page::edit(1)
				home/page.php		Controller\Page::index('edit', 1)
			*/

			$args = (array) self::args();
			$path = '';
			$candidates = array('index' => $args);
			while (count($args) > 0) {
				$arg = array_shift($args);
				if (!preg_match('|^[a-z]\w+$|', $arg)) break;
				if ($path) $path .= '/' . $arg;
				else $path = $arg;
				$candidates[$path] = $args;
			} 

			$class = NULL;
			foreach(array_reverse($candidates) as $path => $params){
				$basename = basename($path);
				$dirname = dirname($path);
				$class_namespace = '\\Controller\\CGI\\';
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

			\Controller\CGI::$CURRENT = $controller;
			_CONF('runtime.controller_action', $action);
			_CONF('runtime.controller_params', $params);

			$controller->__pre_action($action, $params);
			call_user_func_array(array($controller, $action), $params);
			$controller->__post_action($action, $params);
			
		}

		static function exception($e) {
			$message = $e->getMessage();
			if ($message) {
				$file = \Model\File::relative_path($e->getFile());
				$line = $e->getLine();
				error_log(sprintf("\033[31m\033[4mERROR\033[0m \033[1m%s\033[0m", $message));
				$trace = array_slice($e->getTrace(), 1, 5);
				foreach ($trace as $n => $t) {
					error_log(sprintf("    %d) %s%s() in %s on line %d", $n + 1,
									$t['class'] ? $t['class'].'::':'', 
									$t['function'],
									\Model\File::relative_path($t['file']),
									$t['line']));

				}
			}

			if (PHP_SAPI != 'cli') {
				while(@ob_end_clean());	//清空之前的所有显示
				header('HTTP/1.1 500 Internal Server Error');
			}		
		}

		static $AJAX=array();
	
		protected static $form = array();
		protected static $files = array();
		protected static $args = array();
		protected static $get = array();
	
		protected static $route;
		protected static $url;
	
		static function setup(){
	
			if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) 
				|| (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase'))!="off")) ){
				self::stripslashes($_GET);
				self::stripslashes($_POST);
				self::stripslashes($_COOKIE);
			}
	
			$route = $_SERVER['PATH_INFO'];
			if (!$route) $route = $_SERVER['ORIG_PATH_INFO'];
			$route = preg_replace('/^[\/ ]*|[\/ ]*$|'.preg_quote(_CONF('system.url_suffix')).'$/iu','', $route);
	
			self::$route = $route;
			$args = array();
			if(preg_match_all('|(.*?[^\\\])\/|', $route.'/', $parts)){
				foreach($parts[1] as $part) {
					$args[] = strtr($part, array('\/'=>'/'));
				}
			}
	
			self::$args = $args;
			self::$get = $_GET;
			self::$form = array_merge($_POST, $_GET);
			self::$files = $_FILES;
	
			$query=$_GET;
			self::$url = URI::url(self::$route, $query);
	
			if($_POST['_ajax']){
	
				self::$AJAX['widget']=$_POST['_widget'];			
				self::$AJAX['object']=$_POST['_object'];
				self::$AJAX['event']=$_POST['_event'];
				self::$AJAX['mouse']=$_POST['_mouse'];
				self::$AJAX['view']=$_POST['_view'];
	
				unset(self::$form['_ajax']);
				unset(self::$form['_data']);
				unset(self::$form['_widget']);
				unset(self::$form['_object']);
				unset(self::$form['_event']);
				unset(self::$form['_mouse']);
				unset(self::$form['_view']);
	
			}
	
		}
	
		static function & get($name=NULL) {
			if ($name) {
				return self::$get[$name];
			} else {
				return self::$get;
			}
		}
	
		static function & form($name=NULL) {
			if ($name) {
				return self::$form[$name];
			} else {
				return self::$form;
			}
		}
	
		static function & AJAX($name=NULL) {
			if ($name) {
				return self::$AJAX[$name];
			} else {
				return self::$AJAX;
			}
		}
	
		static function & route($route = NULL) {
			if (is_null($route)) {
				return self::$route;
			}
			self::$route = $route;
		}
	
		static function & arg($n=0) {
			return self::$args[$n];
		}
	
		static function & args() {
			return self::$args;
		}
	
		static function & file($name) {
			return self::$files[$name];
		}
	
		static function & files() {
			return self::$files;
		}
	}

}