<?php

/**
 * Gini URI Helpers.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright , 24 January, 2014
 **/

/**
 * Define DocBlock.
 **/

namespace Gini {

    const ARRAY_FORM_REGEX = '#^([^\[]+)(\[.*\])$#i';
    const ARRAY_UNIT_REGEX = '#\[(.*?)\]#';

    class URI
    {
        public static function url($url = null, $query = null, $fragment = null)
        {
            if (!$url) {
                $url = \Gini\CGI::route();
            }

            $ui = parse_url($url);

            if (isset($ui['scheme']) && $ui['scheme'] == 'mailto') {
                //邮件地址
                return 'mailto:' . $ui['user'] . '@' . $ui['host'];
            }

            if ($query) {
                if (isset($ui['query'])) {
                    if (is_string($query)) {
                        $query = self::parseQuery($query);
                    }
                    $old_query = self::parseQuery($ui['query']);
                    $ui['query'] = self::buildQuery(array_merge($old_query, $query));
                } else {
                    $ui['query'] = is_string($query) ? $query : self::buildQuery($query);
                }
            }

            if ($fragment) {
                $ui['fragment'] = $fragment;
            }

            if (isset($ui['host'])) {
                $scheme = $ui['scheme']
                    ?? ($_SERVER['HTTP_X_FORWARDED_PROTO']
                        ?? (isset($_SERVER['HTTPS']) ? 'https' : 'http'));
                $url = $scheme . '://';

                if (isset($ui['user'])) {
                    if ($ui['pass']) {
                        $url .= $ui['user'] . ':' . $ui['pass'] . '@';
                    } else {
                        $url .= $ui['user'] . '@';
                    }
                }

                $url .= $ui['host'];

                if (isset($ui['port'])) {
                    $url .= ':' . $ui['port'];
                }

                $url .= '/';
            } else {
                $url = self::base();
            }

            if (isset($ui['path'])) {
                $url .= ltrim($ui['path'], '/');
            }

            if (isset($ui['query'])) {
                $url .= '?' . $ui['query'];
            }

            if (isset($ui['fragment'])) {
                $url .= '#' . $ui['fragment'];
            }

            return $url;
        }

        public static function encode($text)
        {
            return rawurlencode(strtr($text, ['.' => '\.', '/' => '\/']));
        }

        public static function decode($text)
        {
            return strtr($text, ['\.' => '.', '\/' => '/']);
        }

        public static function anchor($url, $text = null, $extra = null, $options = array())
        {
            if ($extra) {
                $extra = ' ' . $extra;
            }
            if (!$text) {
                $text = $url;
            }
            $url = self::url($url, $options['query'], $options['fragment']);

            return '<a href="' . $url . '"' . $extra . '>' . $text . '</a>';
        }

        public static function mailto($mail, $name = null, $extra = null)
        {
            if (!$name) {
                $name = $mail;
            }
            if ($extra) {
                $extra = ' ' . $extra;
            }

            return '<a href="mailto:' . $mail . '"' . $extra . '>' . $name . '</a>';
        }

        protected static $_base;
        protected static $_rurl;
        public static function setup()
        {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? (isset($_SERVER['HTTPS']) ? 'https' : 'http');
            $dir = dirname($_SERVER['SCRIPT_NAME']);
            if (substr($dir, -1) != '/') {
                $dir .= '/';
            }
            self::$_base = $scheme . '://' . $host . $dir;
            $mi = \Gini\Core::moduleInfo(APP_ID);
            self::$_rurl = $mi->rurl ?? ['*' => 'assets'];
        }

        public static function base($base = null)
        {
            if ($base) {
                self::$_base = rtrim($base, '/') . '/';
            } elseif (self::$_base === null) {
                self::setup();
            }

            return self::$_base;
        }

        private static function _rurl_mod($url, $type)
        {
            // $info = \Gini\Core::moduleInfo(APP_ID);
            $config = (array) \Gini\Config::get('system.rurl_mod');
            if ($type) {
                $query = ($config[$type] ?? [])['query'] ?? null;
                $query = $query ? strtr($query, [
                    '$(TIMESTAMP)' => time(),
                    '$(VERSION)' => APP_HASH,
                ]) : null;
            }

            return empty($query) ? $url : self::url($url, $query);
        }

        public static function rurl($path, $type)
        {
            $ui = parse_url($path);
            if (!isset($ui['host'])) {
                $base = self::$_rurl[$type] ?? ((self::$_rurl['*'] ?? '') . '/' . ($type ?? ''));
                $path = self::base() . rtrim($base, '/') . '/' . $path;
            }

            return self::_rurl_mod($path, $type);
        }

        public static function parseQuery($str)
        {
            $query = [];
            $str = ltrim($str, '?');
            $pairs = explode('&', $str);
            foreach ($pairs as $pair) {
                if ($pair === '') continue;
                list($name, $value) = explode('=', $pair, 2);
                $name = rawurldecode($name);
                $value = rawurldecode($value);
                // for backward-compatibilty, parse [], [xxx]
                if (preg_match(ARRAY_FORM_REGEX, $name, $array_matches)) {
                    $name = $array_matches[1];
                    if (!isset($query[$name])) {
                        $query[$name] = [];
                    } else if (!is_array($query[$name])) {
                        $query[$name] = [$query[$name]];
                    }
                    preg_match_all(ARRAY_UNIT_REGEX, $array_matches[2], $unit_matches);
                    $v = &$query[$name];
                    $k = array_pop($unit_matches[1]);
                    foreach ($unit_matches[1] as $unit) {
                        if ($unit === '') {
                            // just refer to last item of the array
                            $vl = count($v);
                            if ($vl < 1) {
                                $v[] = [];
                                $vl++;
                            }
                            $v = &$v[$vl - 1];
                            continue;
                        }
                        if (!isset($v[$unit])) {
                            $v[$unit] = [];
                        }
                        $v = &$v[$unit];
                    }
                    if ($k === '') {
                        $v[] = $value;
                    } else {
                        $v[$k] = $value;
                    }
                } else if (isset($query[$name])) {
                    if (is_array($query[$name])) {
                        $query[$name][] = $value;
                    } else {
                        $query[$name] = [$query[$name], $value];
                    }
                } else {
                    $query[$name] = $value;
                }
            }
            return $query;
        }

        public static function buildQuery($pairs)
        {
            $query = [];
            foreach ($pairs as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $vv) {
                        array_push($query, rawurlencode($k) . '=' . rawurlencode($vv));
                    }
                } else {
                    array_push($query, rawurlencode($k) . '=' . rawurlencode($v));
                }
            }
            return join('&', $query);
        }
    }
}

namespace {

    if (function_exists('URL')) {
        die('URL() was declared by other libraries, which may cause problems!');
    } else {
        function URL($url = null, $query = null, $fragment = null)
        {
            return \Gini\URI::url($url, $query, $fragment);
        }
    }

    if (function_exists('MAILTO')) {
        die('MAILTO() was declared by other libraries, which may cause problems!');
    } else {
        function MAILTO($mail, $name = null, $extra = null)
        {
            return \Gini\URI::mailto($mail, $name, $extra);
        }
    }

    if (function_exists('RURL')) {
        die('RURL() was declared by other libraries, which may cause problems!');
    } else {
        function RURL($path, $type)
        {
            return \Gini\URI::rurl($path, $type);
        }
    }
}
