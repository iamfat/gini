<?php

namespace Model\Cache;

define('MEMCACHE_HOST', '127.0.0.1');
define('MEMCACHE_PORT', 11211);

class Memcache implements \Model\Cache\Driver {

    private $memcache;
    private $memcached = false;

    function __construct() {
        
        if (class_exists('Memcache', false)) {
            $memcache = new Memcache;
            $memcache->connect(MEMCACHE_HOST, MEMCACHE_PORT);
            $this->memcache = $memcache;
        }
        elseif (class_exists('Memcached', false)) {
            $memcache = new Memcached(CACHE_PREFIX);
            $memcache->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
            $memcache->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_PHP);
            if (0 == count($memcache->getServerList())) {
                $memcache->addServer(MEMCACHE_HOST, MEMCACHE_PORT);
            }
            $this->memcached = true;
            $this->memcache = $memcache;
        }
        
    }

    function set($key, $value, $ttl) {
        if (!$this->memcache) return false;
        if ($this->memcached) {
            return $this->memcache->set($key, $value, $ttl);
        }
        else {
            if (false === $this->memcache->replace($key, $value, 0, $ttl)) {
                return $this->memcache->set($key, $value, 0, $ttl);
            }
            return false;
        }
    }
    
    function get($key) {
        if (!$this->memcache) return null;
        $ret = $this->memcache->get($key);
        if ($ret === false) return null;
        return $ret;
    }
    
    function remove($key) {
        if (!$this->memcache) return false;
        return $this->memcache->delete($key);
    }
    
    function flush() {
        if ($this->memcache) $this->memcache->flush();
    }
    
}

