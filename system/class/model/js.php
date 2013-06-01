<?php

namespace Model {

	define('JS_FORMATTED', '/*JSFORMATTED*/');

	class JS {
		
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

}
