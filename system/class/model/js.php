<?php

define('JS_FORMATTED', '/*JSFORMATED*/');

abstract class _JS {
	
	static function run(){
		$args=func_get_args();

		// NO.BUG#254(xiaopei.li@2010.12.18)
		// 若$args中有%，会被sprintf误会 
		// 导致警告：sprintf(): Too few arguments
		if (count($args)>1) {
			$script=call_user_func_array('sprintf', $args);
		}
		else {
			// 经检查(grep)，系统中调用此方法时皆只有一个参数
			$script = $args[0];
		}

		Output::$AJAX['script'] .= $script;
	}
	
	static function dialog($view, $opt=NULL){
		if (is_array($opt)) {
			$opt['data'] = (string) $view;
			Output::$AJAX['dialog']= $opt;
		} else {
			Output::$AJAX['dialog']= (string) $view;
		}
	}

	static function close_dialog(){
		JS::dialog('#close');
	}

	static function quote($str, $quote='"') {
		if (is_scalar($str)) {
			if (is_numeric($str)) {
				return $str;
			}
			elseif (is_bool($str)) {
				return $str ? true : false;
			}
			elseif (is_null($str)) {
				return 'null';
			}
			else {
				return $quote.self::escape($str).$quote;
			}
		}
		else {
			return @json_encode($str);
		}
	}

	static function escape($str) {
		return addcslashes($str, "\\\'\"&\n\r<>");
	}

	static function alert($str){
		JS::run('alert("'.JS::escape($str).'");');
	}
	
	static function confirm($str, $remember = FALSE){
	
		$token='confirm'.hash('md4', $str);
	
		$form = Input::form();
		if(isset($form[$token])) {
			return $form[$token] == 'true';
		}
		
		$str=JS::escape($str);
		$command='Q.retrigger({'.$token.':confirm("'.$str.'")});';
	
		JS::run($command);
		exit();
	}
	
	static function refresh($selector=NULL) {
		JS::run('Q.refresh('.JS::quote($selector).');');
	}

	static function redirect($url){
		session_write_close();
		JS::run('window.location.href="'.JS::escape($url).'";');
	}
	
	static $valid_modes = array('pack', 'mini', 'default');

	static function format($content) {
		if (!$content) return NULL;
		
		$mode = _CONF('page.js_mode');
		
		if (!preg_match('|^'.preg_quote(JS_FORMATTED, '|').'|', $content)) {
			switch ($mode) {
			case 'pack':
			case 'mini':
				Core::load(THIRD_DIR, 'jsmin', '*');
				$content = JS_FORMATTED."\n".JSMin::minify($content);
				break;			
			}
		}
		
		return $content;
	}
	
	// js 
	static function load($js, $params = NULL) {
		static $_lamda_cache;
		
		$output = '';

		if (is_array($js)) {
			foreach ($js as $j) {
				$output .= self::load($j, $params);
			}
			return $output;
		}
		
		$output = '<script type="text/javascript">(function(){';
		
		$js_key = md5($js);
		
		$params = (array) $params;
		ksort($params);
		
		if (!isset($_lamda_cache[$js_key])) {
			list($category, $path) = explode(':', $js, 2);
			if (!$path) {
				$path = $category;
				$category = NULL;
			}
			
			$path = PRIVATE_DIR . 'js/' . $path . '.js';
	
			$path = Core::file_exists($path, $category);
			if (!$path) return '';

			$mtime = filemtime($path);
			
			$lamda = 'jslamda_'.$js_key;
			$prefix_path =  _CONF('system.tmp_dir').'js/';

			$locale = _CONF('system.locale');
			if ($locale) {
				$prefix_path .= $locale.'_';
			}
			$mode = _CONF('page.js_mode');
			if ($mode) {
				$prefix_path .= $mode.'_';
			}

			$cache_file = $prefix_path . $lamda;
			$re_cache = TRUE;
			if (file_exists($cache_file)) {
				$cache_mtime = filemtime($cache_file);
				if ($cache_mtime > $mtime) $re_cache = FALSE;
			}
			else {
				File::check_path($cache_file);
			}
			
			if ($re_cache) {
				$cache = 'Q.'.$lamda.'=function(_oO){';
				$i = 0;
				foreach ($params as $k => $v) {
					$cache .= sprintf('var %s = _oO[%d]; ', $k, $i);
					$i++;
				}
				$cache .= JS::format(@file_get_contents($path));
				$cache .='};';
				@file_put_contents($cache_file, $cache);
			}
			else {
				$cache = @file_get_contents($cache_file);
			}
			
			$output .= $cache;
			
			$_lamda_cache[$js_key] = $lamda;
		}
		else {
			$lamda = $_lamda_cache[$js_key];
		}
		
		$vars = array();
		foreach ($params as $k => $v) {
			$vars[] = $v;
		}
		$output .= 'Q.'.$lamda.'('.json_encode($vars).');';
		$output .= '})();</script>';
		return $output;
	}
	
	static function load_series($js_ser_arr, $async = TRUE) {
		$output = '';
		
		if (!is_array($js_ser_arr)) {
			$js_ser_arr = array($js_ser_arr);
		}
		
		foreach ((array) $js_ser_arr as $js_ser) {
			if (is_array($js_ser)) {
				$file = $js_ser['file'];
				$mode = $js_ser['mode'];
			}
			else {
				$file = $js_ser;
				$mode = _CONF('page.js_mode');
			}
			
			if (FALSE === strpos($file, '://')) {
				$url = self::cache_file($file);
			}
			else {
				$url = $file;
			}
			
			if ($async) {
				$output .= '<script type="text/javascript"> Q.require_js('.JS::quote($url).','.JS::quote($file).'); </script>';
			}
			else {
				$output .= '<script src="'.H($url).'" type="text/javascript"></script>';
			}

		}
		
		return $output;
	}
	
	static function load_sync($js_ser_arr) {
		return self::load_series($js_ser_arr, FALSE);
	}
	
	static function load_async($js_ser_arr) {
		return self::load_series($js_ser_arr, TRUE);
	}

	//获得JS smart对象
	static function smart() {
		return new JS_Smart;
	}

	static function cache_file($f) {
		$js_file = Misc::key('js', $f).'.js'; 
		$cache_file = Cache::cache_filename($js_file);
		$cache_path = ROOT_PATH.WEB_DIR.$cache_file;
		$version = (int)_CONF('page.js_version');
		if (_CONF('debug.js_check_cache')) {
			if (file_exists($cache_path)) {
				$files = array_unique(explode(' ', $f));
				$mtime = 0;
				foreach ($files as $file) {
					$file = trim($file);
					list($category, $file) = explode(':', $file, 2);
					if (!$file) { $file = $category; $category = NULL; }
						if (!$file) continue;
					$path = Core::file_exists(PRIVATE_DIR.'js/'.$file.'.js', $category);
					if ($path) {
						$mtime = max($mtime, filemtime($path));
					}
				}

				if ($mtime <= filemtime($cache_path)) {
					return $cache_file.'?v='.$version;
				}
			}
		}
		elseif (file_exists($cache_path)) {
			return $cache_file.'?v='.$version;
		}
		return URI::url('js', array('f'=>$f, 'v'=>$version));
	}

	static function cache_content($f) {
		
		$files = array_unique(explode(' ', $f));
			
		$content = '';
		foreach ($files as $file) {
			$file = trim($file);
			list($category, $file) = explode(':', $file, 2);
			if (!$file) { $file = $category; $category = NULL; }
			if (!$file) continue;

			$path = Core::file_exists(PRIVATE_DIR.'js/'.$file.'.js', $category);
			if ($path) {
				$content .= self::format(@file_get_contents($path));
			}
		}

		$js_file = Misc::key('js', $f).'.js'; 
		Cache::cache_content($js_file, $content);
		return $content;

	}

}

class JS_Smart {
	
	private $js_str;
	
	function __get($name) {
		$this->js_str .= ($this->js_str ? '.':'').$name;
		return $this;
	}
	
	function __call($method, $params) {
		if ($method === __CLASS__) return;
		$quotes = array();
		if ($params) foreach((array) $params as $param) {
			if ($param[0] == '@') {
				$quotes[] = substr($param, 1);
			}
			else {
				$quotes[] = JS::quote($param);
			}
		}
		$this->js_str .= ($this->js_str ? '.':'').$method.'('.implode(',', $quotes).')';
		
		return $this;
	}
	
	function __toString() {
		return $this->js_str.';';
	}
}

