<?php

namespace Gini\Logger {

    abstract class Handler
    {
        private static $_LEVEL_VALUES = [
            \Psr\Log\LogLevel::EMERGENCY => 800,
            \Psr\Log\LogLevel::ALERT => 700,
            \Psr\Log\LogLevel::CRITICAL => 600,
            \Psr\Log\LogLevel::ERROR => 500,
            \Psr\Log\LogLevel::WARNING => 400,
            \Psr\Log\LogLevel::NOTICE => 300,
            \Psr\Log\LogLevel::INFO => 200,
            \Psr\Log\LogLevel::DEBUG => 100,
        ];

        protected $_name;
        protected $_level;
        protected $_levelValue;

        public function __construct($name, $level, array $options = array())
        {
            $this->_name = $name;
            $this->_level = $level ?: \Psr\Log\LogLevel::DEBUG;
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

}
