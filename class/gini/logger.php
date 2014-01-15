<?php

namespace Gini {

    class Logger {
        
        static function setup() {
            $log = (object) _CONF('system.log');
            openlog($log->ident?:'gini', $log->option?:(LOG_ODELAY|LOG_PID), $log->facility?:LOG_USER);
            register_shutdown_function ('\Gini\Logger::shutdown');
        }

        static function log($message, $ident='common', $priority=LOG_INFO){
            syslog($priority, "[$ident] $message");
        }

        static function shutdown() {
            closelog();
        }
                
    }

}

namespace {

    function _LOG($message, $ident='common', $priority=LOG_INFO) {
        return \Gini\Logger::log($message, $ident, $priority);
    }

}