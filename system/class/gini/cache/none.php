<?php

namespace Gini\Cache;

class None implements \Gini\Cache_Handler {

	function setup() {}

	function set($key, $value, $ttl) { }
	
	function get($key) { }
	
	function remove($key) { }
	
	function flush() { }
	
}

