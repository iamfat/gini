<?php

namespace Model {

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

namespace {

    function T($fmt, $params=null) {
        if (!$fmt) return $fmt;
        if (is_array($fmt)) {
            $fmt = ngettext($fmt[0], $fmt[1], $fmt[2]);
        }
        else {
            $fmt = gettext($fmt);
        }
        return $params ? strtr($fmt, (array)$params) : $fmt;
    }
    
}
