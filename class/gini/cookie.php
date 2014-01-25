<?php

namespace Gini {
    
    class Cookie {
        
        private static $_cookie_file;
        private static $_cookie;
        
        static function set($name, $value, $expire=0) {

            if ($value === null) {
                unset(self::$_cookie[$name]);
            }
            else {
                self::$_cookie[$name] = array(
                    'value' => $value,
                    'expire' => $expire,
                );
            }
            
        }
        
        static function get($name) {
            if (isset($_COOKIE[$name])) {
                return $_COOKIE[$name];
            }
            
            if (isset(self::$_cookie[$name])) {
                return self::$_cookie[$name]['value'];
            }
        }
        
        static function cleanup() {
            self::$_cookie = array();
        }
        
        static function setup() {
            // load cookie file
            self::$_cookie_file = sys_get_temp_dir() . '/gini-cookie/' . posix_getpwuid(posix_getuid())['name'] . '/cookie_' . posix_getsid(0);
            if (file_exists(self::$_cookie_file)) {
                self::$_cookie = (array)json_decode(file_get_contents(self::$_cookie_file), true);
            }
            else {
                self::$_cookie = array();
            }

            // set $_COOKIE
            $now = time();
            foreach (self::$_cookie as $k => $v) {
                if ($v['expire'] > 0 && $v['expire'] < $now) {
                    unset(self::$_cookie[$k]);
                    continue;
                }
                $_COOKIE[$k] = $v['value'];
            }
        }
        
        static function shutdown() {
            if (self::$_cookie_file) {
                // put $_COOKIE contents to cookie file
                if (!file_exists(self::$_cookie_file)) {
                    \Gini\File::ensureDir(dirname(self::$_cookie_file));
                }
                $content = json_encode((array)self::$_cookie, true);
                file_put_contents(self::$_cookie_file, $content);
            }
        }
        
    }
    
}