<?php

namespace Model {

	final class Config {

		static $items = array();

		private static function _load($category, $filename) {
			if (is_file($filename)) {
				if (!isset(self::$items[$category])) self::$items[$category] = array();

				$config = & self::$items[$category];
				$config['#ROOT'] = & self::$items;
				include($filename);
				unset($config['#ROOT']);
			}
			elseif(is_dir($filename)) {
				$base = $filename;
				$dh = opendir($base);
				if ($dh) {
					if (substr($base, -1) != '/') {
						$base .= '/';
					}
					while ($file = readdir($dh)) {
						if ($file[0] == '.') continue;
						self::_load($category, $base . '/' . $file);
					}
					closedir($dh);
				}
			}
		}

		static function load($path, $category=NULL){
			$base = $path.'/'.CONFIG_DIR;
			if ($category) {
				$ffile = $base.'/'.$category.EXT;
				if (is_file($ffile)) {
					self::_load($category, $ffile);
				}
			}
			elseif (is_dir($base)) {
				$dh = opendir($base);
				if ($dh) {
					while($file = readdir($dh)) {
						if ($file[0] == '.') continue;
						self::_load(basename($file, EXT), $base . '/' . $file);
					}
					closedir($dh);
				}
			}
		}
		
		static function export() {
			return self::$items;
		}

		static function import(& $items){
			self::$items = $items;
		}

		static function clear() {
			self::$items = array();	//清空
		}
		
		static function & get($key){
			list($category, $key) = explode('.', $key, 2);
			if ($key === NULL) return self::$items[$category];			
			return self::$items[$category][$key];
		}

		static function set($key, $val){
			list($category, $key) = explode('.', $key, 2);
			if ($key) {
				if ($val === NULL) {
					unset(self::$items[$category][$key]);
				}
				else {
					self::$items[$category][$key]=$val;
				}
			}
			else {
				if ($val === NULL) {
					unset(self::$items[$category]);
				}
				else {
					self::$items[$category];
				}
			}
		}
		
		static function append($key, $val){
			list($category, $key) = explode('.', $key, 2);
			if (self::$items[$category][$key] === NULL) {
				self::$items[$category][$key] = $val;
			} 
			elseif (is_array(self::$items[$category][$key])) {
				self::$items[$category][$key][] = $val;
			}
			else {
				self::$items[$category][$key] .= $val;
			}
		}

		static function setup() {
			self::clear();
			$exp = 300;
			$config_file = APP_PATH . '/.config';
			if (!file_exists($config_file) || filemtime($config_file) + $exp < time()) {
				foreach ((array) \Gini\Core::$PATH_INFO as $path => $info) {
					if ($info->enabled !== FALSE) {
						self::load($info->path);
					}
				}

				if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
					$opt = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
				}
				else {
					$opt = 0;
				}
				file_put_contents($config_file, json_encode((array)self::$items, $opt));
			}
			else {
				self::$items = (array)@json_decode(file_get_contents($config_file), TRUE);
			}

		}


	}

}

namespace {

	function _CONF($key, $value=NULL) {
		if (is_null($value)) {
			return \Model\Config::get($key);
		}
		else {
			\Model\Config::set($key, $value);
		}
	}

}
