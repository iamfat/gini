<?php

/**
 * CGI support.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2014-02-07
 **/

/**
 * Define DocBlock.
 **/

namespace Gini;

class CGI
{
    public static function stripslashes(&$value)
    {
        return is_array($value) ?
                array_map([__CLASS__, __FUNCTION__], $value) :
                stripslashes($value);
    }

    public static function main($argv)
    {
        //从末端开始尝试
        /*
            home/page/edit/1/2

            home/page/index.php Controller\Page\Index::edit(1)
            home/page/index.php Controller\Index::index('edit', 1)
            home/page.php        Controller\Page::edit(1)
            home/page.php        Controller\Page::index('edit', 1)
        */

        $response = static::request(static::route())->execute();
        if ($response) {
            $response->output();
        }
    }

    public static function defaultEnv()
    {
        return [
            'get' => $_GET, 'post' => $_POST,
            'files' => $_FILES, 'route' => static::$route,
            'method' => $_SERVER['REQUEST_METHOD'],
        ];
    }

    public static function request($route, $env = null)
    {
        if (is_null($env)) {
            $env = static::defaultEnv();
        }

        $router = static::router();
        $controller = $router ? $router->dispatch($route, $env) : false;
        if ($controller === false) {
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
                    $class_namespace .= strtr($dirname, ['/' => '\\']).'\\';
                }

                $class = $class_namespace.$basename.'\\Index';
                if (class_exists($class)) {
                    break;
                }

                $class = $class_namespace.$basename;
                if (class_exists($class)) {
                    break;
                }

                $class = $class_namespace.'Controller'.$basename;
                if (class_exists($class)) {
                    break;
                }

                if ($basename != 'index') {
                    $class = $class_namespace.'Index';
                    if (class_exists($class)) {
                        array_unshift($params, $basename);
                        break;
                    }
                }
            }

            if (!$class || !class_exists($class, false)) {
                static::redirect('error/404');
            }

            \Gini\Config::set('runtime.controller_path', $path);
            $controller = \Gini\IoC::construct($class);
            $controller->params = $params;
        }

        \Gini\Config::set('runtime.controller_class', get_class($controller));
        $controller->env = $env;
        return $controller;
    }

    public static function exception($e)
    {
        $message = $e->getMessage();
        if ($message) {
            $file = $e->getFile();
            foreach (\Gini\Core::$MODULE_INFO as $info) {
                if (0 == strncmp($file, $info->path, strlen($info->path))) {
                    $file = "[$info->id] ".\Gini\File::relativePath($file, $info->path);
                    break;
                }
            }
            $line = $e->getLine();
            error_log(sprintf('ERROR %s', $message));
            $trace = array_slice($e->getTrace(), 1, 5);
            foreach ($trace as $n => $t) {
                $file = $t['file'];
                foreach (\Gini\Core::$MODULE_INFO as $info) {
                    if (0 == strncmp($file, $info->path, strlen($info->path))) {
                        $file = "[$info->id] ".\Gini\File::relativePath($file, $info->path);
                        break;
                    }
                }
                error_log(sprintf(
                    '    %d) %s%s() in %s on line %d',
                    $n + 1,
                    $t['class'] ? $t['class'].'::' : '',
                    $t['function'],
                    $file,
                    $t['line']
                ));
            }
        }

        if (PHP_SAPI != 'cli') {
            while (@ob_end_clean()) {
                //清空之前的所有显示
            }
            header('HTTP/1.1 500 Internal Server Error');
        }
    }

    public static function executeAction($action, $params, $form=null)
    {
        $args = [];
        $r = new \ReflectionMethod($action[0], $action[1]);
        $rps = $r->getParameters();
        if (is_numeric(key($params))) {
            // 使用array_pad确保不会因为变量没有默认设值而报错
            // 但是需要考虑默认
            foreach ($rps as $idx => $rp) {
                $args[] = $params[$idx] ?:
                    ($rp->isDefaultValueAvailable() ? $rp->getDefaultValue() : null);
            }
        } else {
            // 如果是有字符串键值的, 尝试通过反射对应变量
            // 可以把form数据合并进去
            $params = array_merge((array)$params, (array)$form);
            foreach ($rps as $rp) {
                $args[] = $params[$rp->name] ?:
                    ($rp->isDefaultValueAvailable() ? $rp->getDefaultValue() : null);
            }
        }
        return call_user_func_array($action, $args);
    }

    public static function content()
    {
        return file_get_contents('php://input');
    }

    protected static $route;
    public static function route($route = null)
    {
        if (is_null($route)) {
            return static::$route;
        }
        static::$route = $route;
    }

    public static function redirect($url = '', $query = null)
    {
        // session_write_close();
        header('Location: '.URL($url, $query), true, 302);
        exit();
    }

    public static function router()
    {
        static $router;
        if (!$router && class_exists('\Gini\CGI\Router')) {
            $router = \Gini\IoC::construct('\Gini\CGI\Router');
            foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
                $moduleClass = '\Gini\Module\\'.strtr($name, ['-' => '', '_' => '', '/' => '']);
                if (!$info->error && method_exists($moduleClass, 'cgiRoute')) {
                    call_user_func($class.'::cgiRoute', $router);
                }
            }
        }
        return $router;
    }

    public static function setup()
    {
        URI::setup();
        static::$route = trim($_SERVER['PATH_INFO'] ?: $_SERVER['ORIG_PATH_INFO'], '/');
        Session::setup();

        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $_POST = @json_decode(self::content(), true);
        }
    }

    public static function shutdown()
    {
        Session::shutdown();
    }
}
