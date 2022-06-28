<?php

namespace Gini\CGI;

use Gini\Util;
use Gini\Core;
use Gini\IoC;
use Gini\Config;

/**
 * @method self get($route, $dest, ...$args)
 * @method self post($route, $dest, ...$args)
 * @method self put($route, $dest, ...$args)
 * @method self patch($route, $dest, ...$args)
 * @method self delete($route, $dest, ...$args)
 * @method self options($route, $dest, ...$args)
 * @method self any($route, $dest, ...$args)
 */
class Router
{
    private $baseRoute;
    private $options;
    private $rules = [];
    private $middlewares = [];
    private static $METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options'];

    public function __construct($route = '', $options = [])
    {
        $this->baseRoute = trim($route, '/');
        $this->options = $options;
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

    private function _parseRoute($route)
    {
        $route = ($this->baseRoute ? $this->baseRoute . '/' : '') . trim($route, '/');

        if (preg_match_all('/\{(\w+?\??)\}/', $route, $matches)) {
            $params = $matches[1];
        } else {
            $params = [];
        }

        if ($route) {
            $regex = preg_replace('/\{(\w+?\??)\}/', '([^\/]+)', $route);
        } else {
            $regex = '*';
        }

        return [$route, $regex, $params];
    }

    public function rules()
    {
        return $this->rules;
    }

    public function via($route, ...$middlewares)
    {
        if (count($middlewares) == 0) {
            $middlewares = [$route];
            $this->middlewares['*'] = array_merge($this->middlewares['*'] ?? [], $middlewares);
        } else {
            list(, $regex) = $this->_parseRoute($route);
            $this->middlewares[$regex] = array_merge($this->middlewares[$regex] ?? [], $middlewares);
        }
        return $this;
    }

    public static function fromJSON($json)
    {
        $router = new self($json['base'], $json['options']);
        if (isset($json['middlewares'])) {
            $router->middlewares = $json['middlewares'];
        }
        if (isset($json['rules'])) {
            $router->rules = [];
            foreach ($json['rules'] as $k => $rule) {
                $rule['dest'] = is_string($rule['dest']) ? $rule['dest'] : self::fromJSON($rule['dest']);
                $router->rules[$k] = $rule;
            }
        }
        return $router;
    }

    public function toJSON()
    {
        $json = [
            'base' => $this->baseRoute,
            'options' => $this->options,
            'rules' => [],
            'middlewares' => $this->middlewares,
        ];
        foreach ($this->rules as $k => $rule) {
            $rule['dest'] = is_string($rule['dest']) ? $rule['dest'] : $rule['dest']->toJSON();
            $json['rules'][$k] = $rule;
        }
        return $json;
    }

    public function match($methods, $route, $dest, $options = [])
    {
        list($route, $regex, $params) = $this->_parseRoute($route);
        $options += $this->options;

        if (is_callable($dest)) {
            $router = new self($route, $options);
            call_user_func($dest, $router);
            $dest = $router;
        } else {
            $dest = strtr($dest, ['::' => '@']); // allow both '::' and '@'
            if ($dest[0] != '\\') {
                $dest = ($options['classPrefix'] ?? '\\Gini\\Controller\\CGI\\') . $dest;
            }
        }

        if (!is_array($methods)) {
            $methods = [$methods];
        }

        array_walk($methods, function ($method) use ($route, $regex, $dest, $params) {
            $this->rules = [$method . ':' . $regex => [
                'method' => $method,
                'route' => $route,
                'dest' => $dest,
                'params' => $params
            ]] + $this->rules;
        });

        return $this;
    }

    private function _getMiddlewares(string $method, string $route, array $middlewares = [])
    {
        foreach ($this->middlewares as $regex => $matched_middlewares) {
            if ($regex === '*' || preg_match('`^' . $regex . '`i', trim($route, '/'), $matches)) {
                $middlewares = array_merge(
                    $middlewares,
                    $matched_middlewares
                );
            }
        }

        foreach ($this->rules as $key => $rule) {
            if (!($rule['dest'] instanceof self)) {
                continue;
            }

            list($m, $regex) = explode(':', $key, 2);
            if ($m !== '*' && $method !== $m) {
                continue;
            }

            if ($regex !== '*' && !preg_match('`^' . $regex . '`i', trim($route, '/'), $matches)) {
                continue;
            }

            $middlewares = $rule['dest']->_getMiddlewares($method, $route, $middlewares);
        }

        return $middlewares;
    }

    private function _getController($method, $route)
    {
        $controller = null;
        // go through rules
        foreach ($this->rules as $key => $rule) {
            list($m, $regex) = explode(':', $key, 2);
            if ($m !== '*' && $method !== $m) {
                continue;
            }

            if ($regex === '*') {
                $matches = [];
            } elseif (!preg_match('`^' . $regex . '`i', trim($route, '/'), $matches)) {
                continue;
            } else {
                array_shift($matches);
            }

            if ($rule['dest'] instanceof self) {
                $controller = $rule['dest']->_getController($method, $route);
                if ($controller) {
                    break;
                } else {
                    continue;
                }
            }

            $params = array_combine($rule['params'], $matches);

            list($controllerName, $action) = explode('@', $rule['dest'], 2);
            if (!$action) {
                $action = $method . 'Default';
            }

            $controller = \Gini\IoC::construct($controllerName);
            $controller->action = $action;
            $controller->params = $params;
            break;
        }

        return $controller;
    }

    public function dispatch($method, $route)
    {
        $method = strtolower($method ?: 'get');
        $controller = $this->_getController($method, $route);
        if (!$controller) {
            // no matches found in router
            $args = array_map('rawurldecode', explode('/', $route));

            $path = '';
            $candidates = ['/index' => $args] + Util::pathAndArgs($args);
            $class = null;

            foreach (array_reverse($candidates) as $path => $params) {
                $path = strtr(ltrim($path, '/'), ['-' => '', '_' => '']);
                $basename = basename($path);
                $dirname = dirname($path);

                $class_namespace = '\Gini\Controller\CGI\\';
                if ($dirname != '.') {
                    $class_namespace .= strtr($dirname, ['/' => '\\']) . '\\';
                }

                $class = $class_namespace . $basename . '\\Index';
                if (class_exists($class)) {
                    break;
                }

                $class = $class_namespace . $basename;
                if (class_exists($class)) {
                    break;
                }

                $class = $class_namespace . 'Controller' . $basename;
                if (class_exists($class)) {
                    break;
                }

                if ($basename != 'index') {
                    $class = $class_namespace . 'Index';
                    if (class_exists($class)) {
                        array_unshift($params, $basename);
                        break;
                    }
                }
            }

            if (!$class || !class_exists($class, false)) {
                $class = '\Gini\Controller\CGI\Index';
                $params = $args;
            }

            Config::set('runtime.controller_path', $path);
            $controller = IoC::construct($class);
            $controller->params = $params;
        }

        // match middlewares
        $middlewares = $this->_getMiddlewares($method, $route);

        $controller->middlewares = array_unique(array_merge(
            $middlewares,
            $controller->middlewares
        ));

        $controller->app = Core::app();
        return $controller;
    }

    public function cleanUp()
    {
        $this->rules = [];
    }
}
