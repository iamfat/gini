<?php

namespace Gini;

/**
 * Inversion of Control
 *
 */
class IoC
{
    public static function construct()
    {
        $args = func_get_args();
        $class_name = strtolower(array_shift($args));
        // check if the class was overrided?
        return call_user_func_array([new \ReflectionClass($class_name), 'newInstance'], $args);
    }

    public static function bind($name, $callback)
    {
    }

    public static function make($name)
    {

    }

    public static function singleton($name, $callback)
    {

    }

    public static function instance($name, $object)
    {

    }

}
