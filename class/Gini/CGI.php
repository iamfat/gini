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

        $response = self::request(self::$route, ['get' => $_GET, 'post' => $_POST, 'files' => $_FILES, 'route' => self::$route])->execute();
        if ($response) {
            $response->output();
        }
    }

    public static function request($route, array $env = array())
    {
        $args = array_map('rawurldecode', explode('/', $route));

        $path = '';
        $candidates = array('/index' => $args) + Util::pathAndArgs($args);
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
            self::redirect('error/404');
        }

        \Gini\Config::set('runtime.controller_path', $path);
        \Gini\Config::set('runtime.controller_class', $class);
        $controller = \Gini\IoC::construct($class);

        $action = strtr($params[0], ['-' => '', '_' => '']);
        if ($action && $action[0] != '_' && method_exists($controller, 'action'.$action)) {
            $action = 'action'.$action;
            array_shift($params);
        } elseif (method_exists($controller, '__index')) {
            $action = '__index';
        } else {
            self::redirect('error/404');
        }

        $controller->action = $action;
        $controller->params = $params;
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
                error_log(sprintf('    %d) %s%s() in %s on line %d', $n + 1,
                                $t['class'] ? $t['class'].'::' : '',
                                $t['function'],
                                $file,
                                $t['line']));
            }
        }

        if (PHP_SAPI != 'cli') {
            while (@ob_end_clean());    //清空之前的所有显示
            header('HTTP/1.1 500 Internal Server Error');
        }
    }

    public static function content()
    {
        return file_get_contents('php://input');
    }

    protected static $route;
    public static function route($route = null)
    {
        if (is_null($route)) {
            return self::$route;
        }
        self::$route = $route;
    }

    public static function redirect($url = '', $query = null)
    {
        // session_write_close();
        header('Location: '.URL($url, $query), true, 302);
        exit();
    }

    public static function setup()
    {
        URI::setup();
        self::$route = trim($_SERVER['PATH_INFO'] ?: $_SERVER['ORIG_PATH_INFO'], '/');
        Session::setup();
    }

    public static function shutdown()
    {
        Session::shutdown();
    }
}
