<?php

namespace Gini\Logger;

abstract class Handler
{
    private static $_LEVEL_VALUES = [
            Level::EMERGENCY => 800,
            Level::ALERT => 700,
            Level::CRITICAL => 600,
            Level::ERROR => 500,
            Level::WARNING => 400,
            Level::NOTICE => 300,
            Level::INFO => 200,
            Level::DEBUG => 100,
        ];

    protected $_name;
    protected $_level;
    protected $_levelValue;

    public function __construct($name, $level, array $options = array())
    {
        $this->_name = $name;
        $this->_level = $level ?: Level::DEBUG;
        $this->_levelValue = $this->levelValue($this->_level);
    }

    public function log($level, $message, array $context = array())
    {
    }

    public function isLoggable($level)
    {
        return $this->levelValue($level) >= $this->_levelValue;
    }

    public function levelValue($level)
    {
        return self::$_LEVEL_VALUES[$level];
    }
}
