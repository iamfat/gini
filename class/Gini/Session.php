<?php

namespace Gini;

class Session
{
    private static $_handler;
    private static $_lock;

    public static function setup()
    {
        $session_conf = (array) \Gini\Config::get('system.session');
        $cookie_params = (array) $session_conf['cookie'];

        $session_name = $session_conf['name'] ?: 'gini-session';
        $host_hash = sha1($cookie_params['domain'] ?: $_SERVER['HTTP_HOST']);
        ini_set('session.name', $session_name.'_'.$host_hash);

        if ($session_conf['save_handler']) {
            $handler_name = $session_conf['save_handler'];
            // save_handler = internal/files
            if (0 == strncmp($handler_name, 'internal/', 9)) {
                ini_set('session.save_handler', substr($handler_name, 9));
            } else {
                // save_handler = Database
                $class = '\Gini\Session\\'.$handler_name;
                if (class_exists($class)) {
                    self::$_handler = \Gini\IoC::construct($class);
                    session_set_save_handler(self::$_handler, false);
                }
            }
        }

        if ($session_conf['save_path']) {
            session_save_path($session_conf['save_path']);
        }

        if (PHP_SAPI == 'cli') {
            // TODO: find a better way to save and load session id
            $idPath = self::_idPath();
            if (file_exists($idPath)) {
                session_id(file_get_contents($idPath));
            }
        } elseif (isset($_POST['gini-session'])) {
            session_id($_POST['gini-session']);
        } elseif (isset($_SERVER['HTTP_X_GINI_SESSION'])) {
            session_id($_SERVER['HTTP_X_GINI_SESSION']);
        }

        session_set_cookie_params(
            $cookie_params['lifetime'],
            $cookie_params['path'],
            $cookie_params['domain']
        );

        if ($session_conf['gc_maxlifetime']) {
            ini_set('session.gc_maxlifetime', $session_conf['gc_maxlifetime']);
        }

        self::open();
    }

    public static function shutdown()
    {
        foreach ((array) $_SESSION['@ONETIME'] as $token => $remove) {
            if ($remove) {
                unset($_SESSION['@ONETIME'][$token]);
                unset($_SESSION[$token]);
            }
        }

        self::close();
    }

    public static function makeTimeout($token, $timeout = 0)
    {
        if ($timeout > 0) {
            $_SESSION['@TIMEOUT'][$token] = time() + $timeout;
        } else {
            unset($_SESSION['@TIMEOUT'][$token]);
        }
    }

    public static function tempToken($prefix = '', $timeout = 0)
    {
        $token = uniqid($prefix);
        if ($timeout > 0) {
            self::makeTimeout($token, $timeout);
        }

        return $token;
    }

    public static function cleanup($entire = false)
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

    private static function close()
    {
        if (PHP_SAPI == 'cli') return;
        session_commit();
        self::$_lock and self::$_lock->unlock();
    }

    public static function open() {
        if (PHP_SAPI == 'cli') return;

        if ($this->_handlerName == 'internal/redis') {
            self::$_lock = new \Gini\Lock\Redis(ini_get('session.save_path'), session_id());
            self::$_lock->lock(5000);
        }

        set_error_handler(function () {}, E_ALL ^ E_NOTICE);
        session_start();
        restore_error_handler();

        $now = time();
        foreach ((array) $_SESSION['@TIMEOUT'] as $token => $timeout) {
            if ($now > $timeout) {
                unset($_SESSION[$token]);
                unset($_SESSION['@TIMEOUT'][$token]);
            }
        }
    }

}
