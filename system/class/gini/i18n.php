<?php

namespace Gini {

    final class I18N {
    
        static $domain_loaded;
    
        static function setup() {
    
            if (!defined('I18N_PATH')) {
                define('I18N_PATH', APP_PATH . '/i18n');
            }
            
            TRACE('I18N textdomain = '.APP_SHORTNAME);
            bindtextdomain(APP_SHORTNAME, I18N_PATH);
            textdomain(APP_SHORTNAME);
            bind_textdomain_codeset(APP_SHORTNAME, 'UTF-8');
            
            self::set_locale(_CONF('system.locale') ?: 'en_US');
        }
    
        static function set_locale($locale) {
            self::$domain_loaded = null;
    
            $full_locale = ($locale ?: 'en_US').'.UTF-8';
            TRACE("set I18N locale to %s", $full_locale);
            putenv('LANGUAGE='.$full_locale);
            putenv('LANG='.$full_locale);
            setlocale(LC_MESSAGES, $full_locale);
        }
    
    }
    
}

namespace Gini\I18N {
    
    function strip_ctx($s) {
        list($c, $t) = explode("\004", $s, 2);
        return $t ?: $c;
    }

}

namespace {

    function T($fmt, $params=null) {
        if (!$fmt) return $fmt;
        if (is_array($fmt)) {
            $a = \Gini\I18N\strip_ctx($fmt[0]);  //msgid
            $b = \Gini\I18N\strip_ctx($fmt[1]);  //plural msgid
            $c = ngettext($fmt[0], $fmt[1], $fmt[2]);
            if ($c == $fmt[0] || $c == $fmt[1]) {
                $fmt = $fmt[2] == 1 ? $a : $b;
            }
            else {
                $fmt = $c;
            }
        }
        else {
            $a = \Gini\I18N\strip_ctx($fmt);
            $b = gettext($fmt);
            $fmt = ($b == $fmt) ? $a : $b;
        }
        return $params ? strtr($fmt, (array)$params) : $fmt;
    }
    
}
