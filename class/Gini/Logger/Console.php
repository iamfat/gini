<?php

namespace Gini\Logger;

class Console extends Handler
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
        if (PHP_SAPI != 'cli' || !$this->isLoggable($level) ) return;

        $message = "[{ident}] $message";
        $context['ident'] = $this->_name;

        $replacements = [];
        foreach ($context as $key => $val) {
            $replacements['{'.$key.'}'] = $val;
        }

        $message = strtr($message, $replacements);

        printf("%s\n", $message);
    }

}
