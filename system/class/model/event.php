<?php

namespace Model;

final class Event {

	static $events=array();
	
	private $queue=array();
	private $sorted=FALSE;
	private $name;
	
	private $_return;
	private $_pass = FALSE;
	private $_abort = FALSE;
	

	private function _sort(){
		
		uasort($this->queue, function($a, $b){ 
			if ($a->weight != $b->weight) {
				return $a->weight > $b->weight; 
			}

			return $a->order > $b->order;
		});

		$this->sorted=TRUE;
	}

	function __construct($name) {
		$this->name = $name;
	}
	
	function pass() {
		$this->_pass = TRUE;
	}
	
	function abort() {
		$this->_abort = TRUE;
	}
	
	private $triggering = FALSE;
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
				$this->_abort = TRUE;
				$this->_return = $return;
				break;
			}
			
			$this->_pass = FALSE;
			if (is_callable($callback)) {
				$return = call_user_func_array($callback, $params);
			}
			
			if (!$this->_pass) {
				$this->_return = $return;
			}
			
			if ($this->_abort) break;
		}

	}
	
	protected function _bind($return, $weight=0, $key=NULL){

		TRACE('bind("%s", %s, %d, %s)', 
			$this->name, json_encode($return), $weight, $key?:'NULL');

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
	
	static function factory($name, $ensure=TRUE) {
		$e = self::$events[$name];
		if (!$e && $ensure) {
			$e = self::$events[$name] = new Event($name);
		}
		return $e;
	}

	static function & extract_names($selector) {
		return is_array($selector) ? $selector : explode(' ', $selector);
	}
	
	static function bind($events, $callback, $weight=0, $key=NULL) {
		foreach (self::extract_names($events) as $name) {
			self::factory($name)
				->_bind($callback, $weight, $key);
		}
	}
	
	static function trigger() {
		$return = NULL;
		$params = func_get_args();
		$events = array_shift($params);
		foreach (self::extract_names($events) as $name) {
			$e = self::factory($name, FALSE);
			if ($e) {
				$e->_abort = FALSE;
				$e->_return = $return;
				$e->_trigger($params);
				$return = $e->_return;
				$e->_return = NULL;
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
					$key = NULL;
				}
				// $config['xxx'] = array('return'=>'callback:xxx_func', );
				if (is_array($hook) && isset($hook['return'])) {
					$callback = $hook['return'];
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
