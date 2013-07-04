<?php

namespace Model {

    final class I18N {
    
        static $domain_loaded;
    
        static function setup() {
    
            if (!defined('I18N_PATH')) {
                define('I18N_PATH', APP_PATH . '/.i18n');
            }
    
            textdomain('system');
            self::set_locale(_CONF('system.locale'));
         }
    
         static function set_locale($locale) {
             self::$domain_loaded = null;
    
            $full_locale = ($locale ?: 'en_US').'.UTF-8';
            putenv('LC_MESSAGES='.$full_locale);
            setlocale(LC_MESSAGES, $full_locale);
         }
    
        static function load_domain($domain) {
            if (!isset(self::$domain_loaded[$domain])) {
                bindtextdomain($domain, I18N_PATH);
                bind_textdomain_codeset($domain, 'UTF-8');
                self::$domain_loaded[$domain] = true;
            }
        }
    
    }
    
}

namespace {

    function DT($domain, $fmt, $params=null) {

        if (!$fmt) return $fmt;
    
        if ($domain) {
            \Model\I18N::load_domain($domain);
            $fmt = dgettext($domain, $fmt) ?: gettext($fmt);
        }
        else {
            $fmt = gettext($fmt);
        }
    
        return $params ? strtr($fmt, (array)$params) : $fmt;
    
    }
    
    function T($fmt, $params=null) {
        return DT('system', $fmt, $params);
    }
    
    function NT($msgid1, $msgid2, $n) {
        return ngettext($msgid1, $msgid2, $n);
    }
    
    function DNT($domain, $msgid1, $msgid2, $n) {
        return dngettext($domain, $msgid1, $msgid2, $n) ?: ngettext($msgid1, $msgid2, $n);
    }
    
    
}
