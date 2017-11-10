<?php

namespace Gini\CGI;

use \Gini\IoC;

class Middleware {
    static function of($name) {
        $className = '\\Gini\\CGI\\Middleware\\'.strtr($name, ['-' => '', '_' => '', '/' => '']);
        return IoC::construct($className);
    }
}