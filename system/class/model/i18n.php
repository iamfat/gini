<?php

namespace Model {

	use \Gini\Config;
	use \Gini\Core;

	final class I18N {

		static $domain_loaded;

		static function setup() {
			$locale = (Config::get('system.locale') ?: 'zh_CN').'UTF-8';
			setlocale(LC_MESSAGES, $locale);
			putenv('LC_MESSAGE='.$locale);

			self::bind_domain('system');
			textdomain('system');
	 	}
	 	
	 	static function get_items(){
	 		return self::$items;
	 	}
	 	
	 	static function shutdown() {
	 		self::$domain_loaded = NULL;
	 	}
		
		static function HT($domain=NULL, $str, $args=NULL, $options=NULL, $convert_return=FALSE) {
			return Output::H(self::T($domain, $str, $args, $options), $convert_return);
		}
		
		static function T($domain=NULL, $str, $args=NULL) {
			if(is_array($str)){
				foreach($str as &$s){
					$s = self::T($domain, $s, $args);
				}
				return $str;
			}
			
			if ($domain && $domain != 'system') {
				self::bind_domain($domain);
				$str = dgettext($domain, $str) ?: gettext($str);
			}
			else {
				$str = gettext($str);
			}

			if ($args) {
				$str = strtr($str, $args);
			}

			return stripcslashes($str);
		}
	
		static function bind_domain($domain) {

			if (!isset(self::$domain_loaded[$domain])) {
				
				$path = Core::file_exists(I18N_DIR.$locale, $domain);
				if ($path) {
					bindtextdomain($domain, $path);
					bind_textdomain_codeset($domain, 'UTF-8');
				}

				self::$domain_loaded[$domain] = TRUE;
			}
		}

		static function locales() {
			return (array) Config::get('system.locales');
		}
			
	}

}

namespace {

	function T($str, $args=NULL) {
		return Model\I18N::T('system', $str, $args);
	}

	function HT($str, $args=NULL, $convert_return=FALSE){ 	
		return Model\Output::H(T($str, $args), $convert_return);
	}

}

