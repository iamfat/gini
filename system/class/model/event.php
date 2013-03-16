<?php

namespace Model;

final class Event {

	static $events=array();
	
	private $queue=array();
	private $sorted=FALSE;
	private $name;
	
	public $return_value;
	public $next;
	
	private $stop_propagation;

	private function _sort(){
		
		uasort($this->queue, function($a, $b){ 
			if ($a['weight'] != $b['weight']) {
				return $a['weight'] > $b['weight']; 
			}

			return $a['order'] > $b['order'];
		});

		$this->sorted=TRUE;
	}

	function __construct($name) {
		$this->name = $name;
	}
	
	private $triggering = FALSE;
	protected function _trigger(){

		if (!$this->sorted) $this->_sort();
		
		$args = func_get_args();
		array_unshift($args, $this);
		
		foreach($this->queue as &$hook){
			
			$return = $hook['return'];
			// array(object, method)
			if (is_array($return) && count($return) == 2 && is_object($return[0]) ) {
				$callback = $return;
			}
			// callback:\Namespace\Class\Method
			elseif (is_string($return) && 0 == strncmp($return, 'callback:', 9)) {
				$callback = substr($return, 9);
			}
			else {
				$this->stop_propagation = TRUE;
				$this->return_value = $return;
				break;
			}
			
			if (is_callable($callback) && FALSE === call_user_func_array($callback, $args)) {
				$this->stop_propagation = TRUE;
				break;
			}
			
		}

	}
	
	// protected function _trigger_one(){
	// 
	// 	$args = func_get_args();
	// 	$key = $args[0];
	// 	$args[0] = $this;
	// 	
	// 	$this->stop_propagation = TRUE;
	// 	
	// 	$hook = $this->queue[$key];
	// 	if (isset($hook)) {	
	// 		$return = $hook['return'];
	// 		// array(object, method)
	// 		if (is_array($return) && count($return) == 2 && is_object($return[0]) ) {
	// 			$callback = $return;
	// 		}
	// 		// callback:\Namespace\Class\Method
	// 		elseif (is_string($return) && 0 == strncmp($return, 'callback:', 9)) {
	// 			$callback = substr($return, 9);
	// 		}
	// 		else {
	// 			$this->return_value = $return;
	// 			return;
	// 		}
	// 		
	// 		call_user_func_array($callback, $args);
	// 	}
	// 
	// }
	
	protected function _bind($return, $weight=0, $key=NULL){

		TRACE('bind("%s", %s, %d, %s)', $this->name, json_encode($return), $weight, $key?:'NULL');

		$event = array('weight'=>$weight, 'return'=>$return);
		
		if (!$key) {
			if (is_array($return) && count($return) == 2 && is_object($return[0])) {
				$class = get_class($return[0]);
				$key = 'dynamic:'.$class .'.'.$return[1];
			}
			elseif (is_string($return) && 0 == strncmp($return, 'callback:', 9)) {
				$key = $return;
			}
			else {
				$key = 'return:'.json_encode($return);
			} 
		}

		$key = strtolower($key);		
		if (!isset($this->queue[$key])) {
			$event['order'] = count($this->queue);
		}
		$this->queue[$key] =  &$event;
		
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
	
	static function bind($selector, $callback, $weight=0, $key=NULL) {
		foreach (self::extract_names($selector) as $name) {
			self::factory($name)->_bind($callback, $weight, $key);
		}
	}
	
	protected static function & call_wrapper($selector, $method, &$params) {
		$retval = NULL;
		foreach (self::extract_names($selector) as $name) {
			$e = self::factory($name, FALSE);
			if ($e) {
				$e->stop_propagation = FALSE;
				$e->return_value = $retval;
				call_user_func_array(array($e, $method), $params);
				$retval = $e->return_value;
				if ($e->stop_propagation) {
					if ($retval === NULL) {
						$retval = FALSE;
					}
					break;
				}
			}
		}
		return $retval;
	}
	
	static function trigger() {
		$args = func_get_args();
		$selector = array_shift($args);
		return static::call_wrapper($selector, '_trigger', $args);
	}
	
	// static function trigger_one() {
	// 	$args = func_get_args();
	// 	$selector = array_shift($args);
	// 	return static::call_wrapper($selector, '_trigger_one', $args);
	// }

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
