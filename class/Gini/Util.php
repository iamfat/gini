<?php

namespace Gini;

const UPPERCASE_PATTERN = '/[A-Z]/';

class Util
{
    public static function arrayReplaceKeys(array &$arr, $key_arr)
    {
        $new_arr = array();
        foreach ($arr as $k => $v) {
            if (isset($key_arr[$k])) {
                $new_arr[$key_arr[$k]] = $v;
            } else {
                $new_arr[$k] = $v;
            }
        }

        return $arr = $new_arr;
    }

    public static function arrayMergeDeep(array $arr, array $arr1)
    {
        foreach ($arr1 as $k => &$v) {
            if (is_string($k)) {
                if (isset($arr[$k]) && is_array($v)) {
                    if (!is_array($arr[$k])) {
                        $arr[$k] = array();
                    }
                    $arr[$k] = self::arrayMergeDeep($arr[$k], $v);
                } else {
                    $arr[$k] = $v;
                }
            } else {
                $arr[] = $v;
            }
        }

        return $arr;
    }

    public static function randPassword($length = 12, $level = 3)
    {
        list($usec, $sec) = explode(' ', microtime());
        srand(floor((float) $sec + ((float) $usec * 100000)));

        $validchars[1] = '0123456789abcdfghjkmnpqrstvwxyz';
        $validchars[2] = '0123456789abcdfghjkmnpqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $validchars[3] = '0123456789_!@#$%&*()-=+/abcdfghjkmnpqrstvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_!@#$%&*()-=+/';

        $password = '';
        $counter = 0;
        $max_length = strlen($validchars[$level]) - 1;

        while ($counter < $length) {
            $actChar = substr($validchars[$level], rand(0, $max_length), 1);
            // All character must be different
            //if (!strstr($password, $actChar)) {
            $password .= $actChar;
            ++$counter;
            //}
        }

        return $password;
    }

    public static function pathAndArgs(array $argv, $guessCase = false)
    {
        $path = '';
        $candidates = [];

        while (count($argv) > 0) {
            $arg = array_shift($argv);
            if ($guessCase) {
                $arg = implode('', array_map('ucwords', explode('_', strtr($arg, ['-' => '_']))));
            }
            if (!preg_match('|^[a-z][a-z0-9-_]+$|i', $arg)) {
                break;
            }
            $path .= '/' . $arg;
            $candidates[$path] = $argv;
        }

        return $candidates;
    }

    public static function parseArgs($str)
    {
        //TODO: should parse more complex string
        return explode(' ', $str);
    }

    private static function _convertShortOpts($opts)
    {
        // parse shortopts: e.g.   vo:h => ['v', 'o:', 'h']
        preg_match_all('/([a-z])(:+)?/', $opts, $parts);
        return array_combine($parts[1], $parts[2]);
    }

    private static function _convertLongOpts($opts)
    {
        // parse longopts like: ['version', 'output:', 'help']
        $t = [];
        foreach ($opts as $o) {
            if (preg_match('/^(\w+)(:*)$/', $o, $p)) {
                $t[$p[1]] = $p[2];
            }
        }
        return $t;
    }

    public static function getOpt($argv, $options, array $longopts = [])
    {
        if (!is_array($options)) {
            $shortopts = self::_convertShortOpts($options);
        }

        $longopts = self::_convertLongOpts($longopts);

        $opt = ['_' => []];
        $passthru = false;
        try {
            while (count($argv) > 0) {
                $v = array_shift($argv);
                if ($passthru || $v[0] != '-') {
                    $opt['_'][] = $v;
                    continue;
                }

                if ($v == '--') {
                    // Usage: abc -- xxx xx
                    $passthru = true;
                    continue;
                }

                if ($v[1] == '-') {
                    $okv = explode('=', substr($v, 2), 2);
                    $okey = $okv[0] ?? null;
                    $oval = $okv[1] ?? null;
                    if ($okey && isset($longopts[$okey])) {
                        $o = $longopts[$okey];
                        if ($o == ':' || $o == '::') {
                            if (!$oval) {
                                $oval = array_shift($argv);
                            }
                            $opt[$okey] = $oval ?: false;
                        } else {
                            if (isset($opt[$okey])) {
                                $opt[$okey] = [$opt[$okey]];
                                $opt[$okey][] = true;
                            } else {
                                $opt[$okey] = true;
                            }
                        }
                    }
                } else {
                    foreach (str_split(substr($v, 1)) as $okey) {
                        if (isset($shortopts[$okey])) {
                            $o = $shortopts[$okey];
                            if ($o == ':' || $o == '::') {
                                $oval = array_shift($argv);
                                if (!$oval) {
                                    throw new \Exception('missing arguments for -' . $v[1]);
                                }
                                $opt[$okey] = $oval;
                            } else {
                                if (isset($opt[$okey])) {
                                    $opt[$okey] = [$opt[$okey]];
                                    $opt[$okey][] = true;
                                } else {
                                    $opt[$okey] = true;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('\Gini\Util::getOpt error: ' . $e->getMessage());
        }

        return $opt;
    }

    private static $inflector;
    private static function getInflector()
    {
        if (!self::$inflector && class_exists('\Doctrine\Inflector\InflectorFactory')) {
            self::$inflector = \Doctrine\Inflector\InflectorFactory::create()->build();
        }
        return self::$inflector;
    }

    public static function singularize($str)
    {
        $inflector = self::getInflector();
        return $inflector ? $inflector->singularize($str) : $str;
    }

    public static function pluralize($str)
    {
        $inflector = self::getInflector();
        return $inflector ? $inflector->pluralize($str) : $str;
    }

    public static $hyphenateCache = [];
    public static function hyphenate(string $name)
    {
        if (!isset(self::$hyphenateCache[$name])) {
            self::$hyphenateCache[$name] = preg_replace_callback(UPPERCASE_PATTERN, function ($matches) {
                return '-' . mb_strtolower($matches[0]);
            }, $name);
        }
        return self::$hyphenateCache[$name];
    }
}
