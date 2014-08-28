<?php

namespace Gini\Logger;

class JSONSysLog extends Handler
{
    private static $_LEVEL2PRIORITY = [
        Level::EMERGENCY => \LOG_EMERG,
        Level::ALERT => \LOG_ALERT,
        Level::CRITICAL => \LOG_CRIT,
        Level::ERROR => \LOG_ERR,
        Level::WARNING => \LOG_WARNING,
        Level::NOTICE => \LOG_NOTICE,
        Level::INFO => \LOG_INFO,
        Level::DEBUG => \LOG_DEBUG,
    ];

    public function log($level, $message, array $context = array())
    {
        if (!$this->isLoggable($level)) return;

        if (empty($context)) return;

        $message = "[{$this->_name}] @cee: " . json_encode($context);

        openlog(APP_ID, LOG_ODELAY, LOG_LOCAL0);
        syslog(self::$_LEVEL2PRIORITY[$level], $message);
        closelog();
    }

}
