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
			if(FALSE === call_user_func_array($hook['callback'], $args)) {
				$this->stop_propagation = TRUE;
				break;
			}
		}

	}
	
	protected function _trigger_one(){
		if(!$this->sorted) $this->_sort();

		$args = func_get_args();
		$key = $args[0];
		$args[0] = $this;
		
		$hook = $this->queue[$key];
		if (isset($hook)) {	
			if (FALSE === call_user_func_array($hook['callback'], $args)) {
				$this->stop_propagation = TRUE;
				break;
			}
		}

	}
	
	protected function _bind($callback, $weight=0, $key=NULL){

		$event = array('weight'=>$weight, 'callback'=>$callback);

		if (!$key) {
			if(is_string($callback)) {
				$key = $callback;
			} else {
				$class = $callback[0];
				if(is_object($class)) $class = get_class($class);
				$key = $class .'.'.$callback[1];
			}
		}

		$key = strtolower($key);		
		if (!isset($this->queue[$key])) {
			$event['order'] = count($this->queue);
		}
		$this->queue[$key] =  &$event;
		
	}
	
	static function factory($name, $ensure=TRUE) {
		$e = Event::$events[$name];
		if (!$e && $ensure) {
			$e = Event::$events[$name] = new Event($name);
		}
		return $e;
	}

	static function & extract_names($selector) {
		return is_array($selector) ? $selector : explode(' ', $selector);
	}
	
	static function bind($selector, $callback, $weight=0, $key=NULL) {
		foreach (Event::extract_names($selector) as $name) {
			Event::factory($name)->_bind($callback, $weight, $key);
		}
	}
	
	protected static function & call_wrapper($selector, $method, &$params) {
		$retval = NULL;
		foreach (Event::extract_names($selector) as $name) {
			$e = Event::factory($name, FALSE);
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
		return Event::call_wrapper($selector, '_trigger', $args);
	}
	
	static function trigger_one() {
		$args = func_get_args();
		$selector = array_shift($args);
		return Event::call_wrapper($selector, '_trigger_one', $args);
	}

	static function is_binded($name) {
		return count(Event::factory($name)->queue) > 0;
	}
	
}
