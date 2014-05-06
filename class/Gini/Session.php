<?php

namespace Gini;

class Session
{
    
    private static function _idPath()
    {
        return sys_get_temp_dir() . '/gini-session/' . posix_getpwuid(posix_getuid())['name'] . '/' . posix_getsid(0);
    }

    public static function setup()
    {
        $driver = \Gini\Config::get('system.session_driver');

        if ($driver) {

            $class = '\Gini\Session\\'.$driver;

            self::$driver = \Gini\IoC::construct($class);

            session_set_save_driver ( 'Session::open' , 'Session::close' , 'Session::read' , 'Session::write' , 'Session::destroy' , 'Session::gc' );

        }

        $cookie_params = (array) \Gini\Config::get('system.session_cookie');

        $session_name = \Gini\Config::get('system.session_name') ?: 'gini-session';
        $host_hash = sha1($cookie_params['domain'] ?: $_SERVER['HTTP_HOST']);
        ini_set('session.name', $session_name.'_'.$host_hash);

        if (\Gini\Config::get('system.session_path')) {
            session_save_path(\Gini\Config::get('system.session_path'));
        }

        if (PHP_SAPI=='cli') {
            ini_set('session.use_cookies', 0);
            // TODO: find a better way to save and load session id
            $idPath = self::_idPath();
            if (file_exists($idPath)) {
                session_id(file_get_contents($idPath));
            }
        }

        session_set_cookie_params (
            $cookie_params['lifetime'],
            $cookie_params['path'],
            $cookie_params['domain']
        );

        if ($_POST['gini-session']) {
            session_id($_POST['gini-session']);
        }

        set_error_handler(function () {}, E_ALL ^ E_NOTICE);
        session_start();
        restore_error_handler();

        if (!ini_get('session.use_cookies')) {
            // close session immediately to avoid deadlock
            session_write_close();
        }

        $now = time();
        foreach ((array) $_SESSION['@TIMEOUT'] as $token => $timeout) {
            if ($now > $timeout) {
                unset($_SESSION[$token]);
                unset($_SESSION['@TIMEOUT'][$token]);
            }
        }

    }

    public static function shutdown()
    {
        foreach ((array) $_SESSION['@ONETIME'] as $token => $remove) {
            if ($remove) {
                unset($_SESSION['@ONETIME'][$token]);
                unset($_SESSION[$token]);
            }
        }

        if (!ini_get('session.use_cookies')) {

            $tmp = (array) $_SESSION;

            set_error_handler(function () {}, E_ALL ^ E_NOTICE);
            session_start();
            restore_error_handler();

            foreach (array_keys($_SESSION) as $k) {
                unset($_SESSION[$k]);
            }

            foreach (array_keys($tmp) as $k) {
                $_SESSION[$k] = $tmp[$k];
            }
        }

        // 记录session_id
        session_write_close();

        if (!ini_get('session.use_cookies')) {
            // TODO: find a better way to write down session id
            $idPath = self::_idPath();
            File::ensureDir(dirname($idPath), 0775);
            file_put_contents($idPath, session_id());
        }

    }

    public static function close() { return true; }
    public static function open() { return true; }
    public static function read($id)
    {
        if (!self::$driver) return true;
        return self::$driver->read($id);
    }

    private static $driver;
    public static function write($id, $data)
    {
        if (!self::$driver) return true;
        return self::$driver->write($id, $data);
    }

    public static function destroy($id)
    {
        if (!self::$driver) return true;
        return self::$driver->destroy($id);
    }

    public static function gc($max)
    {
        if (!self::$driver) return true;
        return self::$driver->gc($max);
    }

    public static function makeTimeout($token, $timeout = 0)
    {
        if ($timeout > 0) {
            $_SESSION['@TIMEOUT'][$token] = time() + $timeout;
        } else {
            unset($_SESSION['@TIMEOUT'][$token]);
        }
    }

    public static function tempToken($prefix='', $timeout = 0)
    {
        $token = uniqid($prefix);
        if ($timeout > 0) self::makeTimeout($token, $timeout);
        return $token;
    }

    public static function cleanup($entire=false)
    {
        if ($entire) {
            session_unset();
        }

        foreach (array_keys($_SESSION) as $k) {
            if ($entire || $k[0] != '#') {
                unset($_SESSION[$k]);
            }
        }

    }

    public static function regenerateId()
    {
        if (PHP_SAPI != 'cli-server') {
            session_regenerate_id();
        }
    }

}
