<?php

namespace Gini\Logger;

class SysLog extends Handler
{
    private static $_LEVEL2PRIORITY = [
        \Psr\Log\LogLevel::EMERGENCY => \LOG_EMERG,
        \Psr\Log\LogLevel::ALERT => \LOG_ALERT,
        \Psr\Log\LogLevel::CRITICAL => \LOG_CRIT,
        \Psr\Log\LogLevel::ERROR => \LOG_ERR,
        \Psr\Log\LogLevel::WARNING => \LOG_WARNING,
        \Psr\Log\LogLevel::NOTICE => \LOG_NOTICE,
        \Psr\Log\LogLevel::INFO => \LOG_INFO,
        \Psr\Log\LogLevel::DEBUG => \LOG_DEBUG,
    ];

    public function log($level, $message, array $context = array())
    {
        if (!$this->isLoggable($level)) return;

        $message = "[{ident}] $message";
        $context['ident'] = $this->_name;

        $replacements = [];
        foreach ($context as $key => $val) {
            $replacements['{'.$key.'}'] = $val;
        }

        $message = strtr($message, $replacements);

        syslog(self::$_LEVEL2PRIORITY[$level], $message);
    }

}
