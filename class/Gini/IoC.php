<?php

namespace Gini;

/**
 * Inversion of Control
 *
 */
class IoC
{

    protected static $CALLBACKS = [];

    protected static function key($name)
    {
        return strtolower($name);
    }

    public static function construct()
    {
        $args = func_get_args();
        $name = array_shift($args);
        $key = self::key($name);
        // check if the class was overrided?
        if (isset(static::$CALLBACKS[$key])) {
            $o = static::$CALLBACKS[$key];
            if ($o->singleton) {
                return $o->object ?: $o->object = call_user_func_array($o->callback, $args);
            }

            return call_user_func_array($o->callback, $args);
        }

        return call_user_func_array([new \ReflectionClass($name), 'newInstance'], $args);
    }

    public static function bind($name, $callback)
    {
        static::$CALLBACKS[self::key($name)] = (object) [
            'callback' => $callback,
            'singleton' => false
        ];
    }

    public static function singleton($name, $callback)
    {
        static::$CALLBACKS[self::key($name)] = (object) [
            'callback' => $callback,
            'singleton' => true
        ];
    }

    public static function instance($name, $object)
    {
        static::$CALLBACKS[self::key($name)] = (object) [
            'object' => $object,
            'singleton' => true
        ];
    }

    public static function clear($name)
    {
        unset(static::$CALLBACKS[self::key($name)]);
    }

}
