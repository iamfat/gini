<?php

namespace Gini;

const CONST_PATTERN = '/\{\{\{\s*(.+)\s*\}\}\}/';
const MOUSTACHE_PATTERN = '/\{\{([A-Z0-9_]+?)\s*(?:\:\=\s*(.+?))?\s*\}\}/i';
const BASH_PATTERN = '/\$\{([A-Z0-9_]+?)\s*(?:\:\=\s*(.+?))?\s*\}/i';
const QUOTE_PATTERN = '/^(["\'])(.*)\1$/';

class Config
{
    public static $items = [];

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
        if ($key === null) {
            return self::$items[$category];
        }

        return self::$items[$category][$key];
    }

    public static function set($key, $val)
    {
        list($category, $key) = explode('.', $key, 2);
        if ($key) {
            if ($val === null) {
                unset(self::$items[$category][$key]);
            } else {
                self::$items[$category][$key] = $val;
            }
        } else {
            if ($val === null) {
                unset(self::$items[$category]);
            } else {
                self::$items[$category] = $val;
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

    public static function normalizeEnv($row)
    {
        list($key, $value) = explode('=', trim($row), 2);
        $quote_matches = preg_match(QUOTE_PATTERN, $value);
        if ($quote_matches) {
            $value = preg_replace(QUOTE_PATTERN, '$2', $value);
        } else {
            $value = stripslashes($value);
        }
        $value = trim(preg_replace_callback('/\$\{([A-Z0-9_]+?)\s*(?:\:\=\s*(.+?))?\s*\}/i', function ($matches) {
            $defaultValue = $matches[2] ? trim($matches[2], '"\'') : $matches[0];
            return getenv($matches[1]) ?: $defaultValue;
        }, $value));

        $row = $key . '=' . addslashes($value);
        return $row;
    }

    public static function fetch($env = null, $keepVars = false)
    {
        $env = $env ?: APP_PATH . '/.env';
        if (file_exists($env)) {
            $rows = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($rows as &$row) {
                if (!$row || $row[0] == '#') {
                    continue;
                }
                $row = static::normalizeEnv($row);
                putenv($row);
            }
        }

        $items = [];

        $loadFromDir = function ($base) use (&$items, $keepVars) {
            if (!is_dir($base)) {
                return;
            }

            $dh = opendir($base);
            if ($dh) {
                while ($name = readdir($dh)) {
                    if ($name[0] == '.') {
                        continue;
                    }

                    $file = $base . '/' . $name;
                    if (!is_file($file)) {
                        continue;
                    }

                    $category = pathinfo($name, PATHINFO_FILENAME);
                    if (!isset($items[$category])) {
                        $items[$category] = [];
                    }

                    switch (pathinfo($name, PATHINFO_EXTENSION)) {
                        case 'php':
                            $config = &$items[$category];
                            call_user_func(function () use (&$config, $file) {
                                include $file;
                            });
                            break;
                        case 'yml':
                        case 'yaml':
                            $content = file_get_contents($file);
                            if (!$keepVars) {
                                $content = preg_replace_callback(CONST_PATTERN, function ($matches) {
                                    return constant($matches[1]);
                                }, $content);
                            }

                            $replaceCallback = function ($matches) use ($keepVars) {
                                $envValue = getenv($matches[1]);
                                $defaultValue = $matches[2];
                                $quote_matches = preg_match(QUOTE_PATTERN, $defaultValue);
                                if ($quote_matches) {
                                    $defaultValue = preg_replace(QUOTE_PATTERN, '$2', $defaultValue);
                                } else {
                                    $defaultValue = stripslashes($defaultValue);
                                }
                                $mergedValue = $envValue ?: $defaultValue;
                                if ($keepVars) {
                                    return '${' . $matches[1] . ($mergedValue ? ':=' . addslashes($mergedValue) : '') . '}';
                                }
                                return $mergedValue;
                            };
                            $content = preg_replace_callback(MOUSTACHE_PATTERN, $replaceCallback, $content);
                            $content = preg_replace_callback(BASH_PATTERN, $replaceCallback, $content);
                            $content = trim($content);
                            if ($content) {
                                $config = (array) yaml_parse($content);
                                $items[$category] = \Gini\Util::arrayMergeDeep(
                                    $items[$category],
                                    $config
                                );
                            }
                            break;
                    }
                }
                closedir($dh);
            }
        };

        $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'config');
        foreach ($paths as $path) {
            $loadFromDir($path);
        }

        $env = $_SERVER['GINI_ENV'];
        if ($env) {
            $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'config/@' . $env);
            foreach ($paths as $path) {
                $loadFromDir($path);
            }
        }

        return $items;
    }

    public static function env()
    {
        $env = [];
        $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'config');

        $extractEnvFromDir = function ($base) use (&$env) {
            if (!is_dir($base)) {
                return;
            }

            $dh = opendir($base);
            if ($dh) {
                while ($name = readdir($dh)) {
                    if ($name[0] == '.') {
                        continue;
                    }

                    $file = $base . '/' . $name;
                    if (!is_file($file)) {
                        continue;
                    }

                    switch (pathinfo($name, PATHINFO_EXTENSION)) {
                        case 'yml':
                        case 'yaml':
                            $content = file_get_contents($file);
                            $patterns = ['/\{\{([A-Z0-9_]+?)\s*(?:\:\=\s*(.+?))?\s*\}\}/i', '/\$\{([A-Z0-9_]+?)\s*(?:\:\=\s*(.+?))?\s*\}/i'];
                            foreach ($patterns as $pattern) {
                                if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                                    foreach ($matches as $match) {
                                        $env[$match[1]] = $match[2] ? trim($match[2], '"\'') : '';
                                    }
                                }
                            }
                            break;
                    }
                }
                closedir($dh);
            }
        };

        foreach ($paths as $path) {
            $extractEnvFromDir($path);
        }

        ksort($env);
        return $env;
    }
}
