<?php

namespace Model\Session {

	interface Driver {
		function read($id);
		function write($id, $data);
		function destroy($id);
		function gc($max);
	}

}

namespace Model {

	class Session {

		static function setup(){
			
			if (PHP_SAPI == 'cli') return;	
		
			$driver = _CONF('system.session_driver') ?: 'buildin';

			if ($driver != 'buildin') {
				
				$class = 'Session_'.ucwords($driver);

				self::$driver = new $class;

				session_set_save_driver ( 'Session::open' , 'Session::close' , 'Session::read' , 'Session::write' , 'Session::destroy' , 'Session::gc' );
			
			}

			$session_name = _CONF('system.session_name') ?: 'gini_session';
			
			ini_set('session.name', $session_name);

			session_set_cookie_params (
				_CONF('system.session_lifetime'),
				_CONF('system.session_path'),
				_CONF('system.session_domain')
			);
			
			if ($_POST['qsession']) {
				session_id($_POST['qsession']);
			}
			
			session_start();
			
			$now = Date::time();
			foreach((array)$_SESSION['@TIMEOUT'] as $token => $data) {
				list($ts, $timeout) = $data;
				if ($now - $ts > $timeout) {
					unset($_SESSION[$token]);
					unset($_SESSION['@TIMEOUT'][$token]);
				}
			}
			
			foreach((array)$_SESSION['@ONETIME'] as $token => $data) {
				$_SESSION['@ONETIME'][$token] = TRUE;
			}

		}
		
		static function shutdown(){ 
			if (PHP_SAPI == 'cli') return;

			foreach((array)$_SESSION['@ONETIME'] as $token => $remove) {
				if ($remove) {
					unset($_SESSION['@ONETIME'][$token]);
					unset($_SESSION[$token]);
				}
			}

			session_write_close(); 
		}
		
		static function close() { return TRUE; }
		static function open() { return TRUE; }
		static function read($id) { 
			if (!self::$driver) return TRUE;
			return self::$driver->read($id); 
		}
		
		private static $driver;
		static function write($id, $data) { 
			if (!self::$driver) return TRUE;
			return self::$driver->write($id, $data); 
		}

		static function destroy($id) {
			if (!self::$driver) return TRUE;
			return self::$driver->destroy($id); 
		}
		
		static function gc($max) { 
			if (!self::$driver) return TRUE;
			return self::$driver->gc($max); 
		}
		
		static function make_timeout($token, $timeout = 0) {
			if ($timeout > 0) {
				$_SESSION['@TIMEOUT'][$token] = array(Date::time(), (int)$timeout);
			}
			else {
				unset($_SESSION['@TIMEOUT'][$token]);
			}
		}
		
		static function make_onetime($token) {
			$_SESSION['@ONETIME'][$token] = FALSE;
		}
		
		static function temp_token($prefix='', $timeout = 0) {
			$token = uniqid($prefix, TRUE);
			if ($timeout > 0) self::make_timeout($token, $timeout);
			return $token;
		}

		static function cleanup() {
			foreach ($_SESSION as $k => &$v) {
				if ($k[0] != '#') {
					unset($v);
				}
			}
		}

	}

}
