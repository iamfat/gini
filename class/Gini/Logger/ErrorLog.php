<?php

namespace Gini\Logger;

class ErrorLog extends Handler
{
    private static $_LEVEL2LABEL = [
        Level::EMERGENCY => 'EMERGENCY',
        Level::ALERT => 'ALERT',
        Level::CRITICAL => 'CRITICAL',
        Level::ERROR => 'ERROR',
        Level::WARNING => 'WARNING',
        Level::NOTICE => 'NOTICE',
        Level::INFO => 'INFO',
        Level::DEBUG => 'DEBUG',
    ];

    public function log($level, $message, array $context = array())
    {
        if (!$this->isLoggable($level)) {
            return;
        }

        $message = "[{ident}] $message";
        $context['ident'] = $this->_name;

        $replacements = [];
        $_fillReplacements = function (&$replacements, $context, $prefix = '') use (&$_fillReplacements) {
            foreach ($context as $key => $val) {
                if (is_array($val)) {
                    $_fillReplacements($replacements, $val, $prefix . $key . '.');
                } else {
                    $replacements['{' . $prefix . $key . '}'] = $val;
                }
            }
        };
        $_fillReplacements($replacements, $context);

        $message = strtr($message, $replacements);
        error_log('[' . self::$_LEVEL2LABEL[$level] . '] ' . $message);
    }
}
