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

    class URI
    {
        public static function url($url = null, $query = null, $fragment = null)
        {
            if (!$url) {
                $url = \Gini\CGI::route();
            }

            $ui = parse_url($url);

            if ($ui['scheme'] == 'mailto') {
                //邮件地址
                return 'mailto:'.$ui['user'].'@'.$ui['host'];
            }

            if ($query) {
                if ($ui['query']) {
                    if (is_string($query)) {
                        parse_str($query, $query);
                    }
                    parse_str($ui['query'], $old_query);
                    $ui['query'] = http_build_query(array_merge($old_query, $query));
                } else {
                    $ui['query'] = is_string($query) ? $query : http_build_query($query);
                }
            }

            if ($fragment) {
                $ui['fragment'] = $fragment;
            }

            if ($ui['host']) {
                $url = $ui['scheme'] ?: 'http';
                $url .= '://';

                if ($ui['user']) {
                    if ($ui['pass']) {
                        $url .= $ui['user'].':'.$ui['pass'].'@';
                    } else {
                        $url .= $ui['user'].'@';
                    }
                }

                $url .= $ui['host'];

                if ($ui['port']) {
                    $url .= ':'.$ui['port'];
                }

                $url .= '/';
            } else {
                $url = self::base();
            }

            if ($ui['path']) {
                $url .= ltrim($ui['path'], '/');
            }

            if ($ui['query']) {
                $url .= '?'.$ui['query'];
            }

            if ($ui['fragment']) {
                $url .= '#'.$ui['fragment'];
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
                $extra = ' '.$extra;
            }
            if (!$text) {
                $text = $url;
            }
            $url = self::url($url, $options['query'], $options['fragment']);

            return '<a href="'.$url.'"'.$extra.'>'.$text.'</a>';
        }

        public static function mailto($mail, $name = null, $extra = null)
        {
            if (!$name) {
                $name = $mail;
            }
            if ($extra) {
                $extra = ' '.$extra;
            }

            return '<a href="mailto:'.$mail.'"'.$extra.'>'.$name.'</a>';
        }

        protected static $_base, $_rurl;
        public static function setup()
        {
            $host = $_SERVER['HTTP_HOST'];
            $scheme = $_SERVER['HTTPS'] ? 'https' : 'http';
            $dir = dirname($_SERVER['SCRIPT_NAME']);
            if (substr($dir, -1) != '/') {
                $dir .= '/';
            }
            self::$_base = $scheme.'://'.$host.$dir;

            self::$_rurl = \Gini\Core::moduleInfo(APP_ID)->rurl ?: ['*' => 'assets'];
        }

        public static function base($base = null)
        {
            if ($base) {
                self::$_base = $base;
            } elseif (self::$_base === null) {
                self::setup();
            }

            return self::$_base;
        }

        private static function _rurl_mod($url, $type)
        {
            $info = \Gini\Core::moduleInfo(APP_ID);
            $config = (array) \Gini\Config::get('system.rurl_mod');
            if ($type) {
                $query = $config[$type]['query'];
                $query = $query ? strtr($query, [
                    '$(TIMESTAMP)' => time(),
                    '$(VERSION)' => $info->version,
                ]) : null;
            }

            return empty($query) ? $url : self::url($url, $query);
        }

        public static function rurl($path, $type)
        {
            if (!self::$_base === null) {
                self::setup();
            }

            $base = self::$_rurl[$type] ?: (self::$_rurl['*'].'/'.$type ?: '');
            if (substr($base, -1) != '/') {
                $base .= '/';
            }

            return self::_rurl_mod($base.$path, $type);
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
