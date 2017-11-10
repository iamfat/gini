<?php

namespace Gini\CGI;

class Router
{
    private $baseRoute;
    private $rules = [];
    private $middlewares = [];
    private static $METHODS = ['get', 'post', 'put', 'delete', 'options'];

    public function __construct($route='')
    {
        $this->baseRoute = trim($route, '/');
    }

    public function __sleep()
    {
        return ['baseRoute', 'rules', 'middlewares'];
    }

    public function __call($method, $params)
    {
        if ($method == __FUNCTION__) {
            return;
        }

        $m = strtolower($method);
        if ($m == 'any') {
            array_unshift($params, '*');
            return call_user_func_array([$this, 'match'], $params);
        } elseif (in_array($m, self::$METHODS)) {
            array_unshift($params, $m);
            return call_user_func_array([$this, 'match'], $params);
        }
    }

    public function use($middleware)
    {
        if (is_subclass_of($middleware, '\\Gini\\CGI\\Middleware\\Prototype')) {
            $this->middlewares[] = $middleware;
        }
    }

    public function match($methods, $route, $dest)
    {
        $route = ($this->baseRoute ? $this->baseRoute . '/' : '') . trim($route, '/');
        preg_match_all('/\{(\w+?\??)\}/', $route, $matches);
        $regex = preg_replace('/\{(\w+?\??)\}/', '([^\/]+?)', $route);
        $params = $matches[1];

        if (is_callable($dest)) {
            $router = new self($route);
            call_user_func($dest, $router);
            $dest = $router;
        }

        if (!is_array($methods)) {
            $methods = [$methods];
        }
        array_walk($methods, function ($method) use ($regex, $dest, $params) {
            $this->rules[$method.':'.$regex] = [
                'dest' => $dest,
                'params' => $params,
            ];
        });

        return $this;
    }

    public function dispatch($route, $env, $middlewares=null)
    {
        // go through rules
        $currentMethod = strtolower($env['method'] ?: 'get');
        foreach ($this->rules as $key => $rule) {
            list($requestMethod, $regex) = explode(':', $key, 2);
            if ($requestMethod !== '*' && $currentMethod !== $requestMethod) {
                continue;
            }

            if (!preg_match('`^'.$regex.'`i', trim($route, '/'), $matches)) {
                continue;
            }

            if ($rule['dest'] instanceof self) {
                return $rule['dest']->dispatch($route, $env, $this->middlewares);
            }

            array_shift($matches);
            $params = array_combine($rule['params'], $matches);

            list($controllerName, $action) = explode('@', $rule['dest'], 2);
            if (!$action) {
                $action = $requestMethod.'Default';
            }

            $prefix = preg_quote('Gini\\Controller\\CGI\\');
            $controllerName = preg_replace('/^\\\?('.$prefix.'|)/i', '\\Gini\\Controller\\CGI\\', $controllerName);
            $controller = \Gini\IoC::construct($controllerName);
            $controller->action = $action;
            $controller->params = $params;
            $controller->middlewares = array_merge((array) $middlewares, (array) $this->middlewares);
            return $controller;
        }

        return false;
    }

    public function cleanUp()
    {
        $this->rules = [];
    }
}
