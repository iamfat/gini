<?php

namespace Gini\Session {

    interface Driver {
        function read($id);
        function write($id, $data);
        function destroy($id);
        function gc($max);
    }

}

namespace Gini {

    class Session {

        static function setup() {
            
            $driver = _CONF('system.session_driver') ?: 'built_in';

            if ($driver != 'built_in') {
                
                $class = '\\Gini\\Session\\'.$driver;

                self::$driver = new $class;

                session_set_save_driver ( 'Session::open' , 'Session::close' , 'Session::read' , 'Session::write' , 'Session::destroy' , 'Session::gc' );
            
            }

            $session_name = _CONF('system.session_name') ?: 'gini-session';
            $host_hash = hash('md4', $_SERVER['HTTP_HOST']);
            ini_set('session.name', $session_name.'_'.$host_hash);

            if (_CONF('system.session_path')) {
                session_save_path(_CONF('system.session_path'));
            }

            if (PHP_SAPI=='cli') {
                Cookie::setup();
            }
            
            $cookie_params = (array) _CONF('system.session_cookie');
            session_set_cookie_params (
                $cookie_params['lifetime'],
                $cookie_params['path'],
                $cookie_params['domain']
            );
            
            if ($_POST['gini-session']) {
                session_id($_POST['gini-session']);
            }
            
            set_error_handler(function(){}, E_ALL ^ E_NOTICE);
            session_start();
            restore_error_handler();

            if (PHP_SAPI=='cli') {
                // close session immediately to avoid deadlock
                session_write_close();
            }

            $now = time();
            foreach((array)$_SESSION['@TIMEOUT'] as $token => $timeout) {
                if ($now > $timeout) {
                    unset($_SESSION[$token]);
                    unset($_SESSION['@TIMEOUT'][$token]);
                }
            }
            
        }
        
        static function shutdown(){ 

            foreach((array)$_SESSION['@ONETIME'] as $token => $remove) {
                if ($remove) {
                    unset($_SESSION['@ONETIME'][$token]);
                    unset($_SESSION[$token]);
                }
            }

            if (PHP_SAPI == 'cli') {
                
                $tmp = (array) $_SESSION;
                
                set_error_handler(function(){}, E_ALL ^ E_NOTICE);
                session_start();
                restore_error_handler();
                
                foreach(array_keys($_SESSION) as $k) {
                    unset($_SESSION[$k]);
                }
                
                foreach(array_keys($tmp) as $k) {
                    $_SESSION[$k] = $tmp[$k];
                }
            }

            // 记录session_id
            session_write_close(); 

            if (PHP_SAPI == 'cli') {
                if (Cookie::get(session_name()) != session_id()) {
                    Cookie::set(session_name(), session_id());            
                }
                Cookie::shutdown();
            }
            
        }
        
        static function close() { return true; }
        static function open() { return true; }
        static function read($id) { 
            if (!self::$driver) return true;
            return self::$driver->read($id); 
        }
        
        private static $driver;
        static function write($id, $data) { 
            if (!self::$driver) return true;
            return self::$driver->write($id, $data); 
        }

        static function destroy($id) {
            if (!self::$driver) return true;
            return self::$driver->destroy($id); 
        }
        
        static function gc($max) { 
            if (!self::$driver) return true;
            return self::$driver->gc($max); 
        }
        
        static function makeTimeout($token, $timeout = 0) {
            if ($timeout > 0) {
                $_SESSION['@TIMEOUT'][$token] = time() + $timeout;
            }
            else {
                unset($_SESSION['@TIMEOUT'][$token]);
            }
        }
        
        static function tempToken($prefix='', $timeout = 0) {
            $token = uniqid($prefix);
            if ($timeout > 0) self::makeTimeout($token, $timeout);
            return $token;
        }

        static function cleanup($entire=false) {
            if ($entire) {
                session_unset();
            }

            foreach (array_keys($_SESSION) as $k) {
                if ($entire || $k[0] != '#') {
                    unset($_SESSION[$k]);
                }
            }

        }
        
        static function regenerateId() {
            if (PHP_SAPI == 'cli') {
                session_id(null);
            }
            elseif (PHP_SAPI != 'cli-server') {
                session_regenerate_id();
            }
        }
        
    }

}
