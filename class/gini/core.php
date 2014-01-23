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

        static function fetch_info($path) {

            /*
            $shortname; $path;
            $name; $description; $version;
            $dependencies;
            */

            if ($path[0] != '/' && $path[0] != '.') {
                // 相对路径
                $path = $_SERVER['GINI_MODULE_BASE_PATH'] . '/' . $path;
            }

            // $path = realpath($path);

            $info_script = $path.'/gini.json';
            if (!file_exists($info_script)) return null;

            $info = (object)@json_decode(@file_get_contents($info_script), true);

            if (!is_array($info->dependencies)) $info->dependencies = [];

            if (!$info->shortname) {
                $info->shortname = basename($path);
            }

            if ($info->shortname != 'gini' && !isset($info->dependencies['gini'])) {
                $info->dependencies['gini'] = '*';
            }

            $info->path = $path;

            return $info;
        }

        static function path_info($shortname) {
            return self::$PATH_INFO[$shortname] ?: false;
        }
        
        static function checkVersion($version, $versionRequired) {
            if ($versionRequired != '*' &&  preg_match('/^\s*(<=|>=|<|>|=)?\s*(.+)$/', $versionRequired, $parts)) {
                if (!version_compare($version, $parts[2], $parts[1] ?: '>=')) {
                    return false;
                }
            }
            return true;
        }

        static function import($path, $version='*', $parent=null) {

            TRACE("import($path)");
            
            if (isset(self::$PATH_INFO[$path])) {
                $info = self::$PATH_INFO[$path];
                $path = $info->path;
            }
            else {
                if ($path[0] != '/') {
                    $shortname = $path;
                    // 相对路径
                    if ($parent) {
                        $npath = $parent->path . '/modules/'.$path;
                    }
                    else {
                        $npath = 'modules/'.$path;
                    }

                    $path = is_dir($npath) ? $npath : $_SERVER['GINI_MODULE_BASE_PATH'] . '/'.$path;
                }
                else {
                    $shortname = basename($path);
                }
                
                $path = realpath($path);
                $info = self::fetch_info($path);
                if ($info === null) {
                    // throw new \Exception("{$shortname} required but missing!");
                    if ($parent) {
                        $parent->error = "\"{$shortname}/{$version}\" missing!";
                    }
                    return false;
                }
                $info->path = $path;
            }
            
            if (!$info) return false;
 
            if ($parent) {
                if (!self::checkVersion($info->version, $version)) {
                    $parent->error = "{$info->shortname}/{$version} required!";
                    // throw new \Exception("{$info->shortname}/{$version} required but not match!");
                    return false;
                }
            }
            
            if (isset(self::$PATH_INFO[$path])) return self::$PATH_INFO[$path];
            
            foreach ((array)$info->dependencies as $app => $version) {
                if (!$app) continue;
                self::import($app, $version, $info);
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
            return $info;
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
                $file = Core::phar_file_exists($base, $name.'.php', $scope);
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
            foreach (array_reverse((array) self::$PATH_INFO) as $name => $info) {
                $class = '\\'.str_replace('-', '_', $name);
                !method_exists($class, 'exception') or call_user_func($class.'::exception', $e);
            }

            !method_exists('\\Gini\\Application', 'exception') or \Gini\Application::exception($e);
            exit(1);
        }

        static function error($errno , $errstr, $errfile, $errline, $errcontext) {
            throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
        }

        static function assertion($file, $line, $code) {
            throw new \ErrorException($code, 0, 1, $file, $line);
        }

        static function start(){

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

            define('CLASS_DIR', 'class');
            define('VIEW_DIR', 'view');
            define('RAW_DIR', 'raw');
            define('DATA_DIR', 'data');
            define('CACHE_DIR', 'cache');

            $info = self::import(SYS_PATH);

            if (isset($_SERVER['GINI_APP_PATH'])) {
                $app_path = $_SERVER['GINI_APP_PATH'];
                define('APP_PATH', $app_path);
                $info = self::import(APP_PATH);
            }
            else {
                define('APP_PATH', SYS_PATH);
            }

            define('APP_SHORTNAME', $info->shortname);

            Config::setup();
            Event::setup();

            !method_exists('\\Gini\\Application', 'setup') or \Gini\Application::setup();
            foreach (self::$PATH_INFO as $name => $info) {
                $class = '\\'.str_replace('-', '_', $name);
                if (!$info->error && method_exists($class, 'setup')) {
                    call_user_func($class.'::setup');
                }
            }

            global $argv;
            !method_exists('\\Gini\\Application', 'main') or \Gini\Application::main($argv);
        }

        static function shutdown() {
            foreach (array_reverse(self::$PATH_INFO) as $name => $info) {
                $class = '\\'.str_replace('-', '_', $name);
                if (!$info->error && method_exists($class, 'shutdown')) {
                    call_user_func($class.'::shutdown');
                }
            }
            !method_exists('\\Gini\\Application', 'shutdown') or \Gini\Application::shutdown();    
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
                    $indent = count($_TRACE_INDENTS) > 0 ? end($_TRACE_INDENTS) : 0;
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

    function _G($key, $value = null) {
        if (is_null($value)) {
            return isset(\Gini\Core::$_G[$key]) ? \Gini\Core::$_G[$key] : null;
        }
        else {
            \Gini\Core::$_G[$key] = $value;
        }
    }

    if (function_exists('s')) {
        die("s() was declared by other libraries, which may cause problems!");
    }
    else {
        function s() {
            $args = func_get_args();
            if (count($args) > 1) {
                call_user_func_array('sprintf', $args);
            }
            else {
                return $args[0];
            }
        }    
    }

    if (function_exists('H')) {
        die("H() was declared by other libraries, which may cause problems!");
    }
    else {
        function H(){
            $args = func_get_args();
            if (count($args) > 1) {
                $str = call_user_func_array('sprintf', $args);
            }
            else {
                $str = $args[0];
            }
            return htmlentities(iconv('UTF-8', 'UTF-8//IGNORE', $str), ENT_QUOTES, 'UTF-8');
        }
    }

    if (function_exists('V')) {
        die("V() was declared by other libraries, which may cause problems!");
    }
    else {
        function V($path, $vars=null) {
            return new \Gini\View($path, $vars);
        }
    }
}
