<?php

namespace Model {

	use \Gini\Core;

	final class I18N {

		static $domain_loaded;

		static function setup() {

			if (!defined('I18N_PATH')) {
				define('I18N_PATH', APP_PATH . '/.i18n');
			}

			textdomain('system');
			self::set_locale(_CONF('system.locale'));
	 	}
	 		 	
	 	static function set_locale($locale) {
	 		self::$domain_loaded = NULL;

			$full_locale = ($locale ?: 'en_US').'.UTF-8';
			putenv('LC_ALL='.$full_locale);
			setlocale(LC_MESSAGES, $full_locale);
	 	}
					
		static function load_domain($domain) {
			if (!isset(self::$domain_loaded[$domain])) {
				bindtextdomain($domain, I18N_PATH);
				bind_textdomain_codeset($domain, 'UTF-8');
				self::$domain_loaded[$domain] = TRUE;
			}
		}

	}

}

namespace {

	function D_() {
		$args = func_get_args();
		$domain = array_shift($args);
		$fmt = array_shift($args);

		if (!$fmt) return $fmt;

		if ($domain) {
			\Model\I18N::load_domain($domain);
			$fmt = dgettext($domain, $fmt) ?: gettext($fmt);
		}
		else {
			$fmt = gettext($fmt);
		}

		return count($args) > 0 ? vsprintf($fmt, $args) : $fmt;

	}

	function __() {
		$args = func_get_args();
		array_unshift($args, 'system');
		return call_user_func_array('D_', $args);
	}

	function N_($msgid1, $msgid2, $n) {
		return ngettext($msgid1, $msgid2, $n);
	}

	function DN_($domain, $msgid1, $msgid2, $n) {
		return dngettext($domain, $msgid1, $msgid2, $n) ?: ngettext($msgid1, $msgid2, $n);
	}

	function H_(){ 	
		$args = func_get_args();
		return H(call_user_func_array('__', $args));
	}

	function e_() {
		$args = func_get_args();
		echo call_user_func_array('__', $args);
	}

	function HD_(){ 	
		$args = func_get_args();
		return H(call_user_func_array('D_', $args));
	}

	function eD_() {
		$args = func_get_args();
		echo call_user_func_array('D_', $args);
	}

	function eH_() {
		$args = func_get_args();
		echo call_user_func_array('H_', $args);
	}

	function eH_D() {
		$args = func_get_args();
		echo call_user_func_array('HD_', $args);
	}

}

