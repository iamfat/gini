<?php

namespace Gini\Module;

class Prototype
{
    private $_calls;

    public static function setup()
    {
    }

    public static function shutdown()
    {
    }

    public static function exception($e)
    {
    }

    public static function diagnose()
    {
    }

    public function __call($method, $params)
    {
        if ($method !== __FUNCTION__ && isset($this->_calls[$method])) {
            return call_user_func_array($this->_calls[$method], $params);
        }
    }

    public function register($method, $func)
    {
        $this->_calls[$method] = $func;
    }
}
