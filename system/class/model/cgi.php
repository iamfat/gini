<?php

namespace Model {

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

		static function main($argv) {
			$accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
			while (NULL !== ($accept = array_pop($accepts))) {
				list($mime,) = explode(';', $accept, 2);
				$dispatcher = $dispatchers[$mime];
				if ($dispatcher) {
					return call_user_func($dispatcher, $argv);
				}
			}

			return self::default_dispatcher($argv);	
		}

		static function default_dispatcher($argv) {
			
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
				if (!preg_match('|^[a-z-_][\w-]*$|', $arg)) break;
				$arg = strtolower(strtr($arg, '-', '_'));
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

		protected static $form, $files, $get, $post;
		protected static $args, $route;

		static function setup(){

			URI::setup();

			if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) 
				|| (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase'))!="off")) ){
				self::stripslashes($_GET);
				self::stripslashes($_POST);
				self::stripslashes($_COOKIE);
			}

			self::$route = $route = ltrim($_SERVER['PATH_INFO'] ?: $_SERVER['ORIG_PATH_INFO'], '/');

			$args = array();
			if(preg_match_all('|(.*?[^\\\])\/|', $route.'/', $parts)){
				foreach($parts[1] as $part) {
					$args[] = strtr($part, array('\/'=>'/'));
				}
			}

			self::$args = $args;

			self::$get = $_GET;
			self::$post = $_POST;
			self::$files = $_FILES;
			self::$form = array_merge($_POST, $_GET);

			unset($_GET);
			unset($_POST);
			unset($_FILES);
		}

		static function & form($mode = '*') {
			switch($mode) {
			case 'g':
				return self::$get;
			case 'p':
				return self::$post;
			default:
				return self::$form;
			}
		}

		static function & ajax() {
			return self::$ajax;
		}

		static function & route($route = NULL) {
			if (is_null($route)) {
				return self::$route;
			}
			self::$route = $route;
		}

		static function & args() {
			return self::$args;
		}

		static function & files() {
			return self::$files;
		}

		static function redirect($url='', $query=NULL) {
		    session_write_close();
			header('Location: '. URL($url, $query), TRUE, 302);
			exit();
		}
		
		static function shutdown() { 
		}
	}
	
}

namespace {

	function H($str){
		return htmlentities(iconv('UTF-8', 'UTF-8//IGNORE', $str), ENT_QUOTES, 'UTF-8');
	}

	function eH($str) {
		echo H($str);
	}

	function e($str) {
		echo $str;
	}	

}
