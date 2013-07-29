<?php

/**
 *
 * @package    Core
 * @author     黄嘉
 * @copyright  (c) 2009 基理科技
 */

namespace Gini {

    final class Core {

        static $_G;
        static $PATH_INFO;
        static $PATH_TO_SHORTNAME;

        static function short_path($path) {
            $path_arr = explode('/', $path);
            $num = count($path_arr);
            for ($i=$num;$i>1;$i--) {
                $base = implode('/', array_slice($path_arr, 0, $i));
                if (isset(self::$PATH_TO_SHORTNAME[$base])) {
                    $rpath = $i == $num ? '' : implode('/', array_slice($path_arr, $i));
                    return self::$PATH_TO_SHORTNAME[$base] . '/' . $rpath;
                }
            }
        }

        static function shortname($path) {
            $path_arr = explode('/', $path);
            $num = count($path_arr);
            for ($i=$num;$i>1;$i--) {
                $base = implode('/', array_slice($path_arr, 0, $i));
                if (isset(self::$PATH_TO_SHORTNAME[$base])) {
                    return self::$PATH_TO_SHORTNAME[$base];
                }
            }
        }

        static function fetch_info($path) {

            /*
            $shortname; $path;
            $name; $description; $version;
            $dependencies;
            */

            if ($path[0] != '/' && $path[0] != '.') {
                // 相对路径
                $path = $_SERVER['GINI_APP_BASE_PATH'] . '/' . $path;
            }

            $path = realpath($path);

            $info_script = $path.'/gini.json';
            if (!file_exists($info_script)) return null;

            $info = (object)@json_decode(@file_get_contents($info_script), true);

            $info->dependencies = (array) $info->dependencies;        

            if (!$info->shortname) {
                $info->shortname = basename($path);
            }

            if ($info->shortname != 'system') {
                $info->dependencies['system'] = '*';
            }

            $info->path = $path;

            return $info;
        }

        static function path_info($shortname) {
            return self::$PATH_INFO[$shortname] ?: null;
        }

        static function import($path) {

            if ($path[0] != '/') {
                // 相对路径
                $path = $_SERVER['GINI_APP_BASE_PATH'] . '/' . $path;
            }

            $path = realpath($path);
            if (isset(self::$PATH_TO_SHORTNAME[$path])) return;

            // 先加载dependencies
            $info = self::fetch_info($path);
            if (!$info) return;
                        
            foreach ((array)$info->dependencies as $app => $version) {
                self::import($app);
            }

            $inserted = false;
            foreach ((array) self::$PATH_INFO as $b_shortname => $b_info) {

                if (!$inserted && 
                    (isset($b_info->dependencies[$info->shortname]) || $b_shortname == APP_SHORTNAME)
                ) {
                    $path_info[$info->shortname] = $info;
                    $inserted = true;
                }

                $path_info[$b_shortname] = $b_info;
            }

            if (!$inserted) {
                $path_info[$info->shortname] = $info;
            }

            self::$PATH_INFO = $path_info;
            self::$PATH_TO_SHORTNAME[$path] = $info->shortname;
        }

        static function autoload($class){
            //定义类后缀与类路径的对应关系
            $class = strtolower($class);
            $path = str_replace('\\', '/', $class);

            if (isset($GLOBALS['gini.class_map'])) {
                if (isset($GLOBALS['gini.class_map'][$path])) {
                    require_once($GLOBALS['gini.class_map'][$path]);
                }
                return;
            }

            $file = Core::load(CLASS_DIR, $path);
        }

        static function load($base, $name, $scope=null) {
            if (is_array($base)) {
                foreach($base as $b){
                    $file = Core::load($b, $name, $scope);
                    if ($file) return $file;
                }
            }
            elseif (is_array($name)) {
                foreach($name as $n){
                    $file = Core::load($base, $n, $scope);
                    if ($file) return $file;
                }
            }
            else {
                $file = Core::phar_file_exists($base, $name.EXT, $scope);
                if ($file) {
                     require_once($file);
                    return $file;
                }
            }
            return false;
        }

        static function phar_file_exists($phar, $file, $scope=null) {

            if (is_null($scope)) {
                foreach (array_reverse(array_keys((array)self::$PATH_INFO)) as $scope) {
                    $file_path = self::phar_file_exists($phar, $file, $scope);
                    if ($file_path) return $file_path;
                }
            }
            elseif (isset(self::$PATH_INFO[$scope])) {
                $info = self::$PATH_INFO[$scope];
                $file_path = 'phar://'.$info->path . '/' . $phar . '.phar/' . $file;
                if (file_exists($file_path)) return $file_path;

                $file_path = $info->path . '/' . $phar . '/' . $file;
                if (file_exists($file_path)) return $file_path;

            }
            
            return null;        
        }

        static function file_exists($file, $scope = null) {

            if (is_null($scope)) foreach (array_reverse(array_keys((array)self::$PATH_INFO)) as $scope) {
                $file_path = self::file_exists($file, $scope);
                if ($file_path) return $file_path;
            }
            elseif (isset(self::$PATH_INFO[$scope])) {
                $info = self::$PATH_INFO[$scope];
                $file_path = $info->path . '/' . $file;
                if (file_exists($file_path)) return $file_path;
            }

            return null;
        }

        static function phar_file_paths($base, $file) {

            foreach ((array) self::$PATH_INFO as $info) {
                $file_path = 'phar://' . $info->path . '/' . $base . '.phar';
                if ($file) $file_path .= '/' . $file;
                
                if (file_exists($file_path)) {
                    $file_paths[] = $file_path;
                    continue;
                }

                $file_path = $info->path . '/' . $base;
                if ($file) $file_path .= '/' . $file;

                if (file_exists($file_path)) {
                    $file_paths[] = $file_path;
                }
            }

            return array_unique((array) $file_paths);
        }

        static function file_paths($file) {

            foreach ((array) self::$PATH_INFO as $info) {
                $file_path = $info->path . '/' . $file;
                if (file_exists($file_path)) {
                    $file_paths[] = $file_path;
                }
            }

            return array_unique((array) $file_paths);
        }

        static function exception($e) {
            \Application::exception($e);
            exit(1);
        }

        static function error($errno , $errstr, $errfile, $errline, $errcontext) {
            throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
        }

        static function assertion($file, $line, $code) {
            throw new \ErrorException($code, 0, 1, $file, $line);
        }

        static function setup(){

            error_reporting(E_ALL & ~E_NOTICE);
            
            spl_autoload_register('\Gini\Core::autoload');
            register_shutdown_function ('\Gini\Core::shutdown');
            set_exception_handler('\Gini\Core::exception');
            set_error_handler('\Gini\Core::error', E_ALL & ~E_NOTICE);

            assert_options(ASSERT_ACTIVE, 1);
            assert_options(ASSERT_WARNING, 0);
            assert_options(ASSERT_QUIET_EVAL, 1);
            assert_options(ASSERT_CALLBACK, '\Gini\Core::assertion');

            mb_internal_encoding('utf-8');
            mb_language('uni');

            self::import(SYS_PATH);

            // $_SERVER['GINI_APP_PATH'] = '/var/lib/gini-apps/hello'
            if (isset($_SERVER['GINI_APP_PATH'])) {
                $app_path = $_SERVER['GINI_APP_PATH'];
                define('APP_PATH', $app_path);
                self::import($app_path);
            }
            else {
                define('APP_PATH', SYS_PATH);
            }

            define('APP_SHORTNAME', self::$PATH_TO_SHORTNAME[APP_PATH]);

            \Application::setup();
        }

        static function main() {
            global $argv;
            \Application::main($argv);
        }

        static function shutdown() {
            \Application::shutdown();    
        }
        
        static function debug_mode() {
            return file_exists(APP_PATH . '/.debug');
        }

        private static $_trace_regex;
        static function is_tracable($mod) {
            if (!isset(self::$_trace_regex)) {
                self::$_trace_regex = file(APP_PATH . '/.debug', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }
            if (count(self::$_trace_regex) == 0) return true;
            foreach (self::$_trace_regex as $regex) {
                if (preg_match('/'.$regex.'/', $mod)) {
                    return true;
                }
            }
            return false;
        }

    }

}

namespace {

    $_TRACE_INDENTS = array();

    function TRACE() {
        
        $args = func_get_args();
        $fmt = array_shift($args);
        if (\Gini\Core::debug_mode()) {
            
            $trace = array_slice(debug_backtrace(), 1, 1);
            $trace = $trace[0];
            $mod = $trace['function'];
            if (isset($trace['class'])) {
                $mod = $trace['class'].$trace['type'].$mod;
            }
            
            if (\Gini\Core::is_tracable($mod)) {
                array_unshift($args, $mod);
                array_unshift($args, posix_getpid());    //pid
                if (PHP_SAPI == 'cli') {
                    global $_TRACE_INDENTS;
                    $indent = end($_TRACE_INDENTS);
                    if ($ident > 0) {
                        $padding = str_pad(' ', $indent);
                    }
                    else {
                        $padding = '';
                    }
                    vfprintf(STDERR, "$padding\e[32m[%d][%s]\e[0m $fmt\n", $args);
                }
                else {
                    error_log(vsprintf("[%d][%s] $fmt", $args));            
                }
            }
            
        }
        
    }

    function TRACE_INDENT_BEGIN($indent) {
        global $_TRACE_INDENTS;
        $_TRACE_INDENTS[] = (int)$indent;
    }

    function TRACE_INDENT_END() {
        global $_TRACE_INDENTS;
        array_pop($_TRACE_INDENTS);
    }

    $_DECLARED;
    
    function TRY_DECLARE($name, $file) {
        global $_DECLARED;
        if (!isset($_DECLARED[$name])) {
            $_DECLARED[$name] = $file;
        }
    }

    function DECLARED($name, $file) {
        global $_DECLARED;
        return  $_DECLARED[$name] == $file;
    }

    function _G($key, $value = null) {
        if (is_null($value)) {
            return isset(\Gini\Core::$_G[$key]) ? \Gini\Core::$_G[$key] : null;
        }
        else {
            \Gini\Core::$_G[$key] = $value;
        }
    }

    function V($path, $vars=null) {
        return new \Model\View($path, $vars);
    }

    function import($path) {
        return \Gini\Core::import($path);
    }

}
