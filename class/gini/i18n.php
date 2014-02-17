<?php

namespace Gini {

    class I18N
    {
        public static function setup()
        {
            if (!defined('I18N_PATH')) {
                define('I18N_PATH', APP_PATH . '/i18n');
            }

            bindtextdomain(APP_ID, I18N_PATH);
            textdomain(APP_ID);
            bind_textdomain_codeset(APP_ID, 'UTF-8');

            self::setLocale(\Gini\Config::get('system.locale') ?: 'en_US');
        }

        public static function setLocale($locale)
        {
            $full_locale = ($locale ?: 'en_US').'.UTF-8';
            \Gini\Logger::of('core')->debug("locale = {locale}", ['locale'=>$full_locale]);

            putenv('LANGUAGE='.$full_locale);
            putenv('LANG='.$full_locale);
            setlocale(LC_MESSAGES, $full_locale);
        }

        public static function stripContext($s)
        {
            list($c, $t) = explode("\004", $s, 2);

            return $t ?: $c;
        }
    }

}

namespace {

    if (function_exists('T')) {
        die("T() was declared by other libraries, which may cause problems!");
    } else {
        function T($fmt, $params=null)
        {
            if (!$fmt) return $fmt;
            if (is_array($fmt)) {
                $a = \Gini\I18N::stripContext($fmt[0]);  //msgid
                $b = \Gini\I18N::stripContext($fmt[1]);  //plural msgid
                $c = ngettext($fmt[0], $fmt[1], $fmt[2]);
                if ($c == $fmt[0] || $c == $fmt[1]) {
                    $fmt = $fmt[2] == 1 ? $a : $b;
                } else {
                    $fmt = $c;
                }
            } else {
                $a = \Gini\I18N::stripContext($fmt);
                $b = gettext($fmt);
                $fmt = ($b == $fmt) ? $a : $b;
            }

            return $params ? strtr($fmt, (array) $params) : $fmt;
        }
    }
}
