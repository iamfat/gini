<?php

namespace Gini {
    
    class Config {
    
        static $items = [];
    
        static function export() {
            return self::$items;
        }
    
        static function import($items){
            self::$items = $items;
        }
    
        static function clear() {
            self::$items = [];    //清空
        }
        
        static function get($key) {
            list($category, $key) = explode('.', $key, 2);
            if ($key === null) return self::$items[$category];            
            return self::$items[$category][$key];
        }
    
        static function set($key, $val) {
            list($category, $key) = explode('.', $key, 2);
            if ($key) {
                if ($val === null) {
                    unset(self::$items[$category][$key]);
                }
                else {
                    self::$items[$category][$key]=$val;
                }
            }
            else {
                if ($val === null) {
                    unset(self::$items[$category]);
                }
                else {
                    self::$items[$category];
                }
            }
        }
        
        static function append($key, $val){
            list($category, $key) = explode('.', $key, 2);
            if (self::$items[$category][$key] === null) {
                self::$items[$category][$key] = $val;
            } 
            elseif (is_array(self::$items[$category][$key])) {
                self::$items[$category][$key][] = $val;
            }
            else {
                self::$items[$category][$key] .= $val;
            }
        }
    
        static function setup() {
            self::clear();
            $exp = 300;
            $config_file = APP_PATH . '/cache/config.json';
            if (file_exists($config_file)) {
                self::$items = (array)@json_decode(file_get_contents($config_file), true);
            }
            else {
                // no cached file, read from original file
                self::$items = self::fetch();
            }
        }

        private static function _load_config_dir($base, &$items){
            if (!is_dir($base)) return;
            
            $dh = opendir($base);
            if ($dh) {
                while($name = readdir($dh)) {
                    if ($name[0] == '.') continue;
                    
                    $file = $base . '/' . $name;
                    if (!is_file($file)) continue;
                    
                    $category = pathinfo($name, PATHINFO_FILENAME);
                    if (!isset($items[$category])) $items[$category] = [];    

                    switch (pathinfo($name, PATHINFO_EXTENSION)) {
                    case 'php':
                        $config = & $items[$category];
                        call_user_func(function() use (&$config, $file) {
                            include($file);
                        });
                        break;
                    case 'yml':
                    case 'yaml':
                        $config = (array) yaml_parse_file($file);
                        $items[$category] = \Gini\Util::array_merge_deep($items[$category], $config);
                        break;
                    }

                }
                closedir($dh);
            }
        }
        
        public static function fetch() {
            
            $items = [];
            
            $paths = \Gini\Core::phar_file_paths(RAW_DIR, 'config');
            foreach ($paths as $path) {
                self::_load_config_dir($path, $items);
            }
            
            if (isset($_SERVER['GINI_ENV'])) {
                $paths = \Gini\Core::phar_file_paths(RAW_DIR, 'config/@'.$_SERVER['GINI_ENV']);
                foreach ($paths as $path) {
                    self::_load_config_dir($path, $items);
                }
            }
           
            return $items;
        }

    }

}

namespace {
    
    function _CONF($key, $value=null) {
        if (is_null($value)) {
            return \Gini\Config::get($key);
        }
        else {
            \Gini\Config::set($key, $value);
        }
    }
    
}
