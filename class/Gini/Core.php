<?php

/**
 * Gini Core.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2014-01-27
 **/

/**
 * Define DocBlock.
 **/

namespace Gini {

    /**
     * Core Class.
     *
     * @author Jia Huang
     **/
    class Core
    {
        /**
         * The array contains all global defined variables.
         *
         * @var array
         **/
        public static $GLOBALS = [];

        /**
         * The array contains all loaded module info.
         *
         * @var array
         **/
        public static $MODULE_INFO = [];

        /**
         * The array contains all module instances
         *
         * @var array
         */
        protected static $MODULE_INSTANCES = [];

        /**
         * Fetch module info from provided path.
         *
         * (object) info
         *     ->id
         *     ->path
         *     ->name
         *     ->description
         *     ->version
         *     ->dependencies
         *     ->build
         *
         * @param string $path Module path
         *
         * @return object|false Module info
         **/
        public static function fetchModuleInfo($path)
        {
            /*
            $id; $path;
            $name; $description; $version;
            $dependencies;
            */
            if ($path[0] != '/' && $path[0] != '.') {
                // 相对路径
                $npath = APP_PATH . '/modules/' . $path;
                $path = is_dir($npath) ? $npath : $_SERVER['GINI_MODULE_BASE_PATH'] . '/' . $path;
            }
            // $path = realpath($path);

            $info_script = $path . '/gini.json';
            if (!file_exists($info_script)) {
                return false;
            }

            $info = (object) @json_decode(@file_get_contents($info_script), true);

            if (!is_array($info->dependencies)) {
                $info->dependencies = [];
            }

            if (!isset($info->id)) {
                $info->id = \Gini\File::relativePath($path, $_SERVER['GINI_MODULE_BASE_PATH']);
            }

            if ($info->id != 'gini' && !isset($info->dependencies['gini'])) {
                $info->dependencies['gini'] = '*';
            }

            $info->moduleClass = '\Gini\Module\\' . strtr($info->id, ['-' => '', '_' => '', '/' => '']);
            $info->path = $path;

            return $info;
        }

        /**
         * Get module info by module id.
         *
         * @param string $id Module Id
         *
         * @return object|false Module Information
         **/
        public static function moduleInfo($id)
        {
            return self::$MODULE_INFO[$id] ?? false;
        }

        public static function saveModuleInfo($info)
        {
            $info_script = $info->path . '/gini.json';
            unset($info->path);
            unset($info->error);
            $json = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            file_put_contents($info_script, $json);
        }

        /**
         * Import a module by its path.
         *
         * @param string $path              Module path
         * @param string $version[optional] Version Requirement
         * @param object $parent[optional]  Parent Module Information
         *
         * @return object|false Module information
         **/
        public static function import($path, $versionRange = '*', $parent = null)
        {
            if (isset(self::$MODULE_INFO[$path])) {
                $info = self::$MODULE_INFO[$path];
                $path = $info->path;
            } else {
                if ($path[0] != '/') {
                    $id = $path;
                    // relative path? disabled recurring structure
                    // if ($parent) {
                    //     $npath = $parent->path . '/modules/'.$path;
                    // } else {
                    //     $npath = 'modules/'.$path;
                    // }

                    // use flat structure
                    $npath = APP_PATH . '/modules/' . $path;
                    $path = is_dir($npath) ? $npath : $_SERVER['GINI_MODULE_BASE_PATH'] . '/' . $path;
                } else {
                    $id = basename($path);
                }

                $path = realpath($path) ?: $path;
                $info = self::fetchModuleInfo($path);
                if ($info === false) {
                    // throw new \Exception("{$id} required but missing!");
                    if ($parent) {
                        $parent->error = "{$id}:{$versionRange} required!";
                    }

                    return false;
                }

                $info->path = $path;
            }

            if (!$info) {
                return false;
            }

            if ($parent) {
                $version = new Version($info->version);
                if (!$version->satisfies($versionRange)) {
                    $parent->error = "{$info->id}:{$versionRange} required!";
                    // throw new \Exception("{$info->id}:{$versionRange} required but not match!");
                    return false;
                }
            }

            if (isset(self::$MODULE_INFO[$path])) {
                return self::$MODULE_INFO[$path];
            }

            foreach ((array) $info->dependencies as $app => $versionRange) {
                if (!$app) {
                    continue;
                }
                self::import($app, $versionRange, $info);
            }

            $inserted = false;
            $module_info = [];
            foreach (self::$MODULE_INFO as $b_id => $b_info) {
                if (
                    !$inserted &&
                    (isset($b_info->dependencies[$info->id]) || (defined('APP_ID') && $b_id == APP_ID))
                ) {
                    $module_info[$info->id] = $info;
                    $inserted = true;
                }

                $module_info[$b_id] = $b_info;
            }

            if (!$inserted) {
                $module_info[$info->id] = $info;
            }

            self::$MODULE_INFO = $module_info;

            if (!isset(self::$MODULE_INSTANCES[$info->id])) {
                $moduleClass = class_exists($info->moduleClass) ?
                    $info->moduleClass : (class_exists('\Gini\Module\Prototype') ? '\Gini\Module\Prototype' : '\stdClass');
                self::$MODULE_INSTANCES[$info->id] = IoC::construct($moduleClass);
            }

            return $info;
        }

        /**
         * Gini Autoloader.
         *
         * @param string $class Autoloading class name
         **/
        public static function autoload($class)
        {
            //定义类后缀与类路径的对应关系
            // $class = strtolower($class);
            $path = str_replace('\\', '/', $class);
            if (isset($GLOBALS['gini.class_map'])) {
                $path = strtolower($path);
                if (isset($GLOBALS['gini.class_map'][$path])) {
                    require_once $GLOBALS['gini.class_map'][$path];
                    return true;
                }
                return false;
            }

            if (defined('GINI_MUST_CACHE_AUTOLOAD')) {
                die("Missing Autoloading Cache!\n");
            }

            return defined('CLASS_DIR') && !!self::_require(CLASS_DIR, $path);
        }

        /**
         * @ignore private method
         **/
        private static function _require($base, $name, $scope = null)
        {
            if (is_array($base)) {
                foreach ($base as $b) {
                    $file = self::_require($b, $name, $scope);
                    if ($file) {
                        return $file;
                    }
                }
            } elseif (is_array($name)) {
                foreach ($name as $n) {
                    $file = self::_require($base, $n, $scope);
                    if ($file) {
                        return $file;
                    }
                }
            } else {
                $file = self::locatePharFile($base, $name . '.php', $scope);
                if ($file) {
                    require_once $file;

                    return $file;
                }
            }

            return false;
        }

        /**
         * Search Gini modules to locate specific file.
         *
         * @param string $phar            Phar or Directory path
         * @param string $file            File path relative to Phar or Directory
         * @param string $scope[optional] Specify one module to locate the file
         *
         * @return string|false Return matched file path when avaiable
         **/
        public static function locatePharFile($phar, $file, $scope = null)
        {
            if (is_null($scope)) {
                foreach (array_reverse(array_keys(self::$MODULE_INFO)) as $scope) {
                    $file_path = self::locatePharFile($phar, $file, $scope);
                    if ($file_path) {
                        return $file_path;
                    }
                }
            } elseif (isset(self::$MODULE_INFO[$scope])) {
                $info = self::$MODULE_INFO[$scope];
                $file_path = 'phar://' . $info->path . '/' . $phar . '.phar/' . $file;
                if (file_exists($file_path)) {
                    return $file_path;
                }

                $file_path = $info->path . '/' . $phar . '/' . $file;
                if (file_exists($file_path)) {
                    return $file_path;
                }
            }

            return false;
        }

        /**
         * Search Gini modules to locate specific file.
         *
         * @param string $file            File path
         * @param string $scope[optional] Specify one module to locate the file
         *
         * @return string|false Return matched file path when avaiable
         **/
        public static function locateFile($file, $scope = null)
        {
            if (is_null($scope)) {
                foreach (array_reverse(array_keys(self::$MODULE_INFO)) as $scope) {
                    $file_path = self::locateFile($file, $scope);
                    if ($file_path) {
                        return $file_path;
                    }
                }
            } elseif (isset(self::$MODULE_INFO[$scope])) {
                $info = self::$MODULE_INFO[$scope];
                $file_path = $info->path . '/' . $file;
                if (file_exists($file_path)) {
                    return $file_path;
                }
            }

            return;
        }

        /**
         * Get all possible module paths (in phar or not) by given file.
         *
         * @param string $base Phar or Directory Base
         * @param string $file File path
         *
         * @return array Return all file paths
         **/
        public static function pharFilePaths($base, $file)
        {
            $file_paths = [];
            foreach (self::$MODULE_INFO as $info) {
                $file_path = 'phar://' . $info->path . '/' . $base . '.phar';
                if ($file) {
                    $file_path .= '/' . $file;
                }

                if (file_exists($file_path)) {
                    $file_paths[] = $file_path;
                    continue;
                }

                $file_path = $info->path . '/' . $base;
                if ($file) {
                    $file_path .= '/' . $file;
                }

                if (file_exists($file_path)) {
                    $file_paths[] = $file_path;
                }
            }
            return array_unique($file_paths);
        }

        /**
         * Get all possible module paths by given file.
         *
         * @param string $file File path
         *
         * @return array Return all file paths
         **/
        public static function filePaths($file)
        {
            foreach (self::$MODULE_INFO as $info) {
                $file_path = $info->path . '/' . $file;
                if (file_exists($file_path)) {
                    $file_paths[] = $file_path;
                }
            }

            return array_unique((array) $file_paths);
        }

        /**
         * @ignore Exception Handler
         **/
        public static function exception($e)
        {
            if (isset($GLOBALS['gini.class_map'])) {
                $moduleInfo = array_reverse(self::$MODULE_INFO);
                array_walk($moduleInfo, function ($info, $name) use ($e) {
                    method_exists($info->moduleClass, 'exception') and call_user_func([$info->moduleClass, 'exception'], $e);
                });
            }
            method_exists('\Gini\Application', 'exception') and \Gini\Application::exception($e);
            exit(1);
        }

        /**
         * @ignore Error Handler
         **/
        public static function error($errno, $errstr, $errfile, $errline)
        {
            // error was suppressed with the @-operator
            if (0 === error_reporting()) {
                return false;
            }
            throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
        }

        /**
         * Function to start the whole gini framework.
         **/
        public static function start()
        {
            spl_autoload_register('\Gini\Core::autoload');
            register_shutdown_function('\Gini\Core::shutdown');
            set_exception_handler('\Gini\Core::exception');

            $env = $_SERVER['GINI_ENV'] ?? 'development';
            error_reporting($env === 'development' ?  (E_ALL & ~E_NOTICE) : (E_ALL & ~(E_NOTICE | E_WARNING)));
            set_error_handler('\Gini\Core::error', E_ALL & ~(E_NOTICE | E_WARNING));

            mb_internal_encoding('utf-8');
            mb_language('uni');

            define('CLASS_DIR', 'class');
            define('VIEW_DIR', 'view');
            define('RAW_DIR', 'raw');
            define('DATA_DIR', 'data');
            define('CACHE_DIR', 'cache');

            $info = self::import(SYS_PATH);
            define('SYS_VERSION', $info->version);

            if (isset($_SERVER['GINI_APP_PATH'])) {
                $app_path = $_SERVER['GINI_APP_PATH'];
                define('APP_PATH', $app_path);
                $info = self::import(APP_PATH);
            } else {
                define('APP_PATH', SYS_PATH);
            }

            define('APP_ID', $info->id);

            // load composer if detected
            $composer_path = APP_PATH . '/vendor/autoload.php';
            file_exists($composer_path) && require_once($composer_path);

            Config::setup();
            Event::setup();

            method_exists('\Gini\Application', 'setup') && \Gini\Application::setup();

            // 生成一个 APP_HASH 用于做版本唯一说明
            define('APP_HASH', sha1(array_reduce(
                self::$MODULE_INFO,
                function ($str, $info) {
                    return $str . ':' . $info->id . '/' . $info->version;
                },
                ''
            )));

            // module setup won't be run before CLASS cache
            if (isset($GLOBALS['gini.class_map'])) {
                array_walk(self::$MODULE_INFO, function ($info, $name) {
                    $class = '\Gini\Module\\' . strtr($name, ['-' => '', '_' => '', '/' => '']);
                    !isset($info->error) and method_exists($info->moduleClass, 'setup')
                        and call_user_func([$info->moduleClass, 'setup']);
                });
            }

            global $argv;
            method_exists('\Gini\Application', 'main') and \Gini\Application::main($argv);
        }

        /**
         * Shutdown handler, called when script finished.
         **/
        public static function shutdown()
        {
            if (isset($GLOBALS['gini.class_map'])) {
                $moduleInfo = array_reverse(self::$MODULE_INFO);
                array_walk($moduleInfo, function ($info, $name) {
                    !isset($info->error) and method_exists($info->moduleClass, 'shutdown')
                        and call_user_func([$info->moduleClass, 'shutdown']);
                });
            }
            method_exists('\Gini\Application', 'shutdown') and \Gini\Application::shutdown();
        }

        public static function module($moduleName)
        {
            return self::$MODULE_INSTANCES[$moduleName];
        }

        public static function app()
        {
            return self::module(APP_ID);
        }
    } // END class

}

namespace {

    /*
     * Shortcut for global variables in Gini
     *
     * @param string $key Name of global variable
     * @param string[optional] string given when setting
     * @return mixed
     **/
    if (function_exists('_G')) {
        die('_G() was declared by other libraries, which may cause problems!');
    } else {
        function _G($key, $value = null)
        {
            if (is_null($value)) {
                return isset(\Gini\Core::$GLOBALS[$key]) ? \Gini\Core::$GLOBALS[$key] : null;
            } else {
                \Gini\Core::$GLOBALS[$key] = $value;
            }
        }
    }

    /*
     * Shortcut for sprintf()
     *
     * @return string
     **/
    if (function_exists('s')) {
        die('s() was declared by other libraries, which may cause problems!');
    } else {
        function s()
        {
            $args = func_get_args();
            if (count($args) > 1) {
                return call_user_func_array('sprintf', $args);
            }

            return $args[0];
        }
    }

    /*
     * Shortcut for htmlentities() + sprintf()
     * e.g. H("Hello, %s!", "world")
     *
     * @return string
     **/
    if (function_exists('H')) {
        die('H() was declared by other libraries, which may cause problems!');
    } else {
        ini_set('mbstring.substitute_character', 'none');
        function H()
        {
            $args = func_get_args();
            if (count($args) > 1) {
                $str = call_user_func_array('sprintf', $args);
            } else {
                $str = $args[0] ?? '';
            }

            // iconv('UTF-8', 'UTF-8//TRANSLIT//IGNORE', $str)
            return htmlentities(mb_convert_encoding($str, 'UTF-8', 'UTF-8'), ENT_QUOTES, 'UTF-8');
        }
    }

    /*
     * Shortcut for json_encode() with JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
     * e.g. H("Hello, %s!", "world")
     *
     * @return string
     **/
    if (function_exists('J')) {
        die('J() was declared by other libraries, which may cause problems!');
    } else {
        function J($v, $opt = 0)
        {
            $opt |= JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

            return @json_encode($v, $opt);
        }
    }

    /*
     * Shortcut for new \Gini\View
     *
     * @param  string          $path Path to the view
     * @param  array[optional] $vars Parameters for the view
     * @return object          \Gini\View object
     **/
    if (function_exists('V')) {
        die('V() was declared by other libraries, which may cause problems!');
    } else {
        function V($path, $vars = null)
        {
            return \Gini\IoC::construct('\Gini\View', $path, $vars);
        }
    }
}
