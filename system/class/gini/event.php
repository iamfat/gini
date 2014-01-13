<?php

namespace Gini {

    class Event {

        static $events=[];
    
        private $queue=[];
        private $sorted=false;
        private $name;
    
        private $_return;
        private $_pass = false;
        private $_abort = false;
    

        private function _sort(){
        
            uasort($this->queue, function($a, $b){ 
                if ($a->weight != $b->weight) {
                    return $a->weight > $b->weight; 
                }

                return $a->order > $b->order;
            });

            $this->sorted=true;
        }

        function __construct($name) {
            $this->name = $name;
        }
    
        function pass() {
            $this->_pass = true;
        }
    
        function abort() {
            $this->_abort = true;
        }
    
        private $triggering = false;
        private function _trigger(array $params){

            if (!$this->sorted) $this->_sort();
        
            array_unshift($params, $this);
        
            foreach($this->queue as $hook){

                $return = $hook->return;
                // array(object, method)
                if (is_array($return) && count($return) == 2 && is_object($return[0]) ) {
                    $callback = $return;
                }
                // callback:\Namespace\Class\Method
                elseif (is_string($return) && 0 == strncmp($return, 'callback:', 9)) {
                    $callback = substr($return, 9);
                }
                else {
                    $this->_abort = true;
                    $this->_return = $return;
                    break;
                }
            
                $this->_pass = false;
                if (is_callable($callback)) {
                    $return = call_user_func_array($callback, $params);
                }
            
                if (!$this->_pass) {
                    $this->_return = $return;
                }
            
                if ($this->_abort) break;
            }

        }
    
        protected function _bind($return, $weight=0, $key=null){

            TRACE('bind("%s", %s, %d, %s)', 
                $this->name, json_encode($return), $weight, $key?:'null');

            $event = (object) ['weight'=>$weight, 'return'=>$return];
        
            if (!$key) {
                if (is_array($return) 
                    && count($return) == 2 && is_object($return[0])) {
                    $class = get_class($return[0]);
                    $key = 'dynamic:'.$class .'.'.$return[1];
                }
                elseif (is_string($return) 
                    && 0 == strncmp($return, 'callback:', 9)) {
                    $key = $return;
                }
                else {
                    $key = 'return:'.json_encode($return);
                } 
            }

            $key = strtolower($key);        
            if (!isset($this->queue[$key])) {
                $event->order = count($this->queue);
            }
        
            $this->queue[$key] = $event;
        
        }
    
        static function factory($name, $ensure=true) {
            $e = self::$events[$name];
            if (!$e && $ensure) {
                $e = self::$events[$name] = new Event($name);
            }
            return $e;
        }

        static function & extract_names($selector) {
            return is_array($selector) ? $selector : [$selector];
        }
    
        static function bind($events, $callback, $weight=0, $key=null) {
            foreach (self::extract_names($events) as $name) {
                self::factory($name)
                    ->_bind($callback, $weight, $key);
            }
        }
    
        static function trigger() {
            $return = null;
            $params = func_get_args();
            $events = array_shift($params);
            foreach (self::extract_names($events) as $name) {
                $e = self::factory($name, false);
                if ($e) {
                    $e->_abort = false;
                    $e->_return = $return;
                    $e->_trigger($params);
                    $return = $e->_return;
                    $e->_return = null;
                    if ($e->_return) {
                        break;
                    }
                }
            }
        
            return $return;
        }
    
        static function is_binded($name) {
            return count(static::factory($name)->queue) > 0;
        }
    
        static function setup() {
            foreach((array)_CONF('hooks') as $event => $event_hooks) {
                foreach ((array) $event_hooks as $key => $hook) {
                    if (!is_string($key)) {
                        $key = null;
                    }
                    // $config['xxx'] = array('return'=>'callback:xxx_func', );
                    if (is_array($hook) && isset($hook['return'])) {
                        $return = $hook['return'];
                        $weight = $hook['weight'];
                    }
                    else {
                        $return = $hook;
                        $weight = 0;
                    }
                    static::bind($event, $return, $weight, $key);
                }
            }
        }

    }
    
}

