<?php

namespace Model {
	
	final class Config {
	
		static $items = array();
	
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
			if (!file_exists($config_file)) {
				throw new ErrorException("Config cache not exists!");
			}
			self::$items = (array)@json_decode(file_get_contents($config_file), TRUE);
		}
		
	}

}

namespace {
	
	use \Model\Config;
	
	function _CONF($key, $value=NULL) {
		if (is_null($value)) {
			return Config::get($key);
		}
		else {
			Config::set($key, $value);
		}
	}
	
}