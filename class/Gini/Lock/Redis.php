<?php

namespace Gini\Lock;

class Redis implements Driver
{
    private $_instances = [];
    private $_resource;
    private $_quorum;

    const CLOCK_DRIFT_FACTOR = 0.01;
    const RETRY_MAX = 3;
    const RETRY_DELAY = 200;

    public function __construct($path, $resource)
    {
        $urls = array_map('trim', explode(',', $path));
        foreach ($urls as $url) {
            $u = parse_url($url);
            if (!$u['host']) {
                continue;
            }
            if ($u['query']) {
                $q = parse_str($u['query']);
            }
            $redis = new \Redis();
            $redis->connect($u['host'], $u['port'] ?: 6379, 0.01);
            $q['auth'] and $redis->auth($q['auth']);
            $this->_instances[$url] = $redis;
        }
        $this->_quorum = min(count($this->_instances), (count($this->_instances) / 2 + 1));
        $this->_resource = $resource.'-lock';
        $this->_token = uniqid();
    }

    private function lockInstance($instance, $ttl)
    {
        return $instance->set($this->_resource, $this->_token, ['NX', 'PX' => $ttl]);
    }

    private function unlockInstance($instance)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';

        return $instance->eval($script, [$this->_resource, $this->_token], 1);
    }

    public function lock($ttl = 2000)
    {
        $retry = self::RETRY_MAX;
        while ($retry--) {
            $n = 0;
            $startTime = microtime(true) * 1000;
            foreach ($this->_instances as $instance) {
                if ($this->lockInstance($instance, $ttl)) {
                    ++$n;
                }
            }
            # Add 2 milliseconds to the drift to account for Redis expires
            # precision, which is 1 millisecond, plus 1 millisecond min drift
            # for small TTLs.
            $drift = ($ttl * self::CLOCK_DRIFT_FACTOR) + 2;
            $validityTime = $ttl - (microtime(true) * 1000 - $startTime) - $drift;
            if ($n >= $this->_quorum && $validityTime > 0) {
                return [
                    'validity' => $validityTime,
                    'resource' => $this->_resource,
                    'token' => $this->_token,
                ];
            } else {
                foreach ($this->_instances as $instance) {
                    $this->unlockInstance($instance);
                }
            }

            // Wait a random delay before to retry
            $delay = mt_rand(floor(self::RETRY_DELAY / 2), self::RETRY_DELAY);
            usleep($delay * 1000);
        }

        return false;
    }

    public function unlock()
    {
        foreach ($this->_instances as $instance) {
            $this->unlockInstance($instance);
        }
    }
}
