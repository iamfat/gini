<?php

namespace Gini {

    class Logger {
        
        protected static $_LOGGERS = [];
        public static function of($name) {
            if (!isset(self::$_LOGGERS[$name])) {
               self::$_LOGGERS[$name] = new Logger($name); 
            }
            return self::$_LOGGERS[$name];
        }
        
        protected $_h;  // monolog handle
        public function __construct($name) {
            $logger = new \Monolog\Logger($name);
            Event::trigger(["monolog[$name].addHandler", "monolog[*].addHandler"], $logger);
            $this->_h = $logger;
        }
        
        public function __call($method, $params) {
            if ($method === __FUNCION__) return;
            return call_user_func_array([$this->_h, $method], $params); 
        }

    }

}