<?php

namespace Model\Cache;

class APC implements \Model\Cache\Driver {

	function set($key, $value, $ttl) {
		return function_exists('apc_store') && @apc_store($key, serialize($value), $ttl);
	}
	
	function get($key) {
		$ret = function_exists('apc_store') && @unserialize(strval(@apc_fetch($key)));
		if ($ret === FALSE) return NULL;
		return $ret;
	}
	
	function remove($key) {
		return @apc_delete($key);
	}
	
	function flush() {
		//@apc_clear_cache();
	}
	
}

