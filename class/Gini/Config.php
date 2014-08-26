<?php

namespace Gini;

class Config
{
    static $items = [];

    public static function export()
    {
        return self::$items;
    }

    public static function import($items)
    {
        self::$items = $items;
    }

    public static function clear()
    {
        self::$items = [];    //清空
    }

    public static function get($key)
    {
        list($category, $key) = explode('.', $key, 2);
        if ($key === null) return self::$items[$category];
        return self::$items[$category][$key];
    }

    public static function set($key, $val)
    {
        list($category, $key) = explode('.', $key, 2);
        if ($key) {
            if ($val === null) {
                unset(self::$items[$category][$key]);
            } else {
                self::$items[$category][$key]=$val;
            }
        } else {
            if ($val === null) {
                unset(self::$items[$category]);
            } else {
                self::$items[$category];
            }
        }
    }

    public static function append($key, $val)
    {
        list($category, $key) = explode('.', $key, 2);
        if (self::$items[$category][$key] === null) {
            self::$items[$category][$key] = $val;
        } elseif (is_array(self::$items[$category][$key])) {
            self::$items[$category][$key][] = $val;
        } else {
            self::$items[$category][$key] .= $val;
        }
    }

    public static function setup()
    {
        self::clear();
        $exp = 300;
        $config_file = APP_PATH . '/cache/config.json';
        if (file_exists($config_file)) {
            self::$items = (array) @json_decode(file_get_contents($config_file), true);
        } else {
            // no cached file, read from original file
            self::$items = self::fetch();
        }
    }

    private static function _load_config_dir($base, &$items)
    {
        if (!is_dir($base)) return;

        $dh = opendir($base);
        if ($dh) {
            while ($name = readdir($dh)) {
                if ($name[0] == '.') continue;

                $file = $base . '/' . $name;
                if (!is_file($file)) continue;

                $category = pathinfo($name, PATHINFO_FILENAME);
                if (!isset($items[$category])) $items[$category] = [];

                switch (pathinfo($name, PATHINFO_EXTENSION)) {
                case 'php':
                    $config = & $items[$category];
                    call_user_func(function () use (&$config, $file) {
                        include($file);
                    });
                    break;
                case 'yml':
                case 'yaml':
                    $content = file_get_contents($file);
                    $content = preg_replace_callback('/\$\((.+?)\)/', function ($matches) {
                        return $_SERVER[$matches[1]] ?: $matches[0];
                    }, $content);
                    $config = \Symfony\Component\Yaml\Yaml::parse($content);
                    $items[$category] = \Gini\Util::arrayMergeDeep($items[$category], $config);
                    break;
                }

            }
            closedir($dh);
        }
    }

    public static function fetch()
    {
        $items = [];

        $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'config');
        foreach ($paths as $path) {
            self::_load_config_dir($path, $items);
        }

        if (isset($_SERVER['GINI_ENV'])) {
            $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'config/@'.$_SERVER['GINI_ENV']);
            foreach ($paths as $path) {
                self::_load_config_dir($path, $items);
            }
        }

        return $items;
    }

}
