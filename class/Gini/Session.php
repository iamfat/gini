<?php

namespace Gini;

class Session
{
    private static $_handlerName;
    private static $_handler;
    private static $_lock;

    public static function setup()
    {
        if (headers_sent()) {
            return;
        }

        $session_conf = (array) \Gini\Config::get('system.session');

        $session_name = $session_conf['name'] ?? 'gini-session';
        $host_hash = sha1($_SERVER['HTTP_HOST'] ?? '');
        ini_set('session.name', $session_name . '_' . $host_hash);

        if (isset($session_conf['save_handler'])) {
            self::$_handlerName = $session_conf['save_handler'];
            // save_handler = internal/files
            if (0 == strncmp(self::$_handlerName, 'internal/', 9)) {
                ini_set('session.save_handler', substr(self::$_handlerName, 9));
            } else {
                // save_handler = Database
                $class = '\Gini\Session\\' . self::$_handlerName;
                if (class_exists($class)) {
                    self::$_handler = \Gini\IoC::construct($class);
                    session_set_save_handler(self::$_handler, false);
                }
            }
        }

        if (isset($session_conf['save_path'])) {
            session_save_path($session_conf['save_path']);
        }

        if (isset($session_conf['gc_maxlifetime'])) {
            ini_set('session.gc_maxlifetime', $session_conf['gc_maxlifetime']);
        }

        if (ini_get('session.use_cookies') === '1') {
            $cookie_params = (array) $session_conf['cookie'];
            session_set_cookie_params(
                $cookie_params['lifetime'],
                $cookie_params['path'],
                $cookie_params['domain'],
                $cookie_params['secure'] ?: false,
                $cookie_params['httponly'] ?: false
            );
        }
    }

    public static function shutdown()
    {
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

    public static function name()
    {
        return session_name();
    }

    public static function id()
    {
        return session_id();
    }

    public static function regenerateId()
    {
        if (session_status() == PHP_SESSION_DISABLED) {
            return;
        }
        self::unlock();
        if (version_compare(PHP_VERSION, '8.0.0') >= 0) {
            $sid = session_create_id();
            session_commit();
            session_id($sid);
            session_start();
        } else {
            session_regenerate_id();
        }
        self::lock();
    }

    public static function open()
    {
        if (
            session_status() === PHP_SESSION_DISABLED
            || session_status() === PHP_SESSION_ACTIVE
        ) {
            return;
        }

        if (isset($_COOKIE[session_name()])) {
            session_id($_COOKIE[session_name()]);
        } elseif (isset($_POST['gini-session'])) {
            session_id($_POST['gini-session']);
        } elseif (isset($_SERVER['HTTP_X_GINI_SESSION'])) {
            session_id($_SERVER['HTTP_X_GINI_SESSION']);
        }

        set_error_handler(function () {
        }, E_ALL ^ E_NOTICE);
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

    public static function close()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        foreach ((array) $_SESSION['@ONETIME'] as $token => $remove) {
            if ($remove) {
                unset($_SESSION['@ONETIME'][$token]);
                unset($_SESSION[$token]);
            }
        }

        session_commit();
    }

    public static function sync()
    {
        session_commit();
        session_start();
    }

    public static function lock()
    {
        $sid = session_id();
        session_commit();
        if (self::$_handlerName == 'internal/redis') {
            self::$_lock = new \Gini\Lock\Redis(ini_get('session.save_path'), $sid);
            self::$_lock->lock(2000); // 2s at the most
        }
        session_start();
    }

    public static function unlock()
    {
        session_commit();
        if (self::$_lock) {
            self::$_lock->unlock();
            self::$_lock = null;
        }
        session_start();
    }
}
