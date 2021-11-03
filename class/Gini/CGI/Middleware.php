<?php

namespace Gini\CGI;

use Gini\IoC;

class Middleware
{
    private static $MIDDLEWARES = [];
    public static function of($name)
    {
        if (!isset(self::$MIDDLEWARES[$name])) {
            $className = '\\Gini\\CGI\\Middleware\\'.strtr($name, ['-' => '', '_' => '', '/' => '\\']);
            self::$MIDDLEWARES[$name] = IoC::construct($className);
        }
        return self::$MIDDLEWARES[$name];
    }
}
