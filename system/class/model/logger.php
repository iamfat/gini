<?php

namespace Model {

	class Logger {
		
		static function get_path($ident = 'common') {
			$tpl_path = _CONF('system.'.$ident.'_log_path');
			if (!$tpl_path) $tpl_path = _CONF('system.log_path') ?: APP_PATH . '/logs/%ident.log';
			return strtr($tpl_path, array('%ident'=>$ident));
		}
		
		static function setup() {
			openlog(_CONF('system.log_ident')?:'gini', _CONF('system.log_option')?:(LOG_ODELAY|LOG_PID), _CONF('system.log_facility')?:LOG_USER);
		}

		static function log($message, $ident='common', $priority=LOG_INFO){
			syslog($priority, "[$ident] $message");
		}

		static function shutdown() {
			closelog();
		}
		
	}

}

namespace {

	function _LOG($message, $ident='common', $priority=LOG_INFO) {
		return \Model\Logger::log($message, $ident, $priority);
	}

}