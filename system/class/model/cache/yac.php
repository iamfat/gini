<?php

namespace Model\Cache;

class YAC implements \Model\Cache\Driver {

    private $_h;

    function __construct() {
        $this->_h = new \Yac();
    }

	function set($key, $value, $ttl) {
		return $this->_h->set($key, $value, $ttl);
	}
	
	function get($key) {
		return $this->_h->get($key);
	}
	
	function remove($key) {
		return $this->_h->delete($key);
	}
	
	function flush() {
        return $this->_h->flush();
	}
	
}

