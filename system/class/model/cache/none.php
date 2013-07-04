<?php

namespace Model\Cache;

class None implements \Model\Cache\Driver {

    function set($key, $value, $ttl) { }
    
    function get($key) { }
    
    function remove($key) { }
    
    function flush() { }
    
}

