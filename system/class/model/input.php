<?php

namespace GR\System\Model {

	use \Gini\Config;
	use \Model\URI;
	
	TRY_DECLARE('\Model\Input', __FILE__);
	
	class Input {
	
		static $AJAX=array();
	
		protected static $form = array();
		protected static $files = array();
		protected static $args = array();
		protected static $get = array();
	
		protected static $route;
		protected static $url;
	
		static function stripslashes(& $value)
		{
			return is_array($value) ?
					array_map(array(__CLASS__, __FUNCTION__), $value) :
					stripslashes($value);
		}
	
		static function setup(){
	
			if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) 
				|| (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase'))!="off")) ){
				Input::stripslashes($_GET);
				Input::stripslashes($_POST);
				Input::stripslashes($_COOKIE);
			}
	
			$route = $_SERVER['PATH_INFO'];
			if (!$route) $route = $_SERVER['ORIG_PATH_INFO'];
			$route = preg_replace('/^[\/ ]*|[\/ ]*$|'.preg_quote(Config::get('system.url_suffix')).'$/iu','', $route);
	
			Input::$route = $route;
			$args = array();
			if(preg_match_all('|(.*?[^\\\])\/|', $route.'/', $parts)){
				foreach($parts[1] as $part) {
					$args[] = strtr($part, array('\/'=>'/'));
				}
			}
	
			Input::$args = $args;
			Input::$get = $_GET;
			Input::$form = array_merge($_POST, $_GET);
			Input::$files = $_FILES;
	
			$query=$_GET;
			Input::$url = URI::url(Input::$route, $query);
	
			if($_POST['_ajax']){
	
				Input::$AJAX['widget']=$_POST['_widget'];			
				Input::$AJAX['object']=$_POST['_object'];
				Input::$AJAX['event']=$_POST['_event'];
				Input::$AJAX['mouse']=$_POST['_mouse'];
				Input::$AJAX['view']=$_POST['_view'];
	
				unset(Input::$form['_ajax']);
				unset(Input::$form['_data']);
				unset(Input::$form['_widget']);
				unset(Input::$form['_object']);
				unset(Input::$form['_event']);
				unset(Input::$form['_mouse']);
				unset(Input::$form['_view']);
	
			}
	
		}
	
		static function & get($name=NULL) {
			if ($name) {
				return Input::$get[$name];
			} else {
				return Input::$get;
			}
		}
	
		static function & form($name=NULL) {
			if ($name) {
				return Input::$form[$name];
			} else {
				return Input::$form;
			}
		}
	
		static function & AJAX($name=NULL) {
			if ($name) {
				return Input::$AJAX[$name];
			} else {
				return Input::$AJAX;
			}
		}
	
		static function & route($route = NULL) {
			if (is_null($route)) {
				return Input::$route;
			}
			Input::$route = $route;
		}
	
		static function & args() {
			return Input::$args;
		}
	
		static function & arg($n=0) {
			return Input::$args[$n];
		}
	
		static function & file($name) {
			return Input::$files[$name];
		}
	
		static function & files() {
			return Input::$files;
		}
		
	}
	
	
}

namespace Model {
	
	if (DECLARED('\Model\Input', __FILE__)) {
		class Input extends \GR\System\Model\Input {}
	}
	
}