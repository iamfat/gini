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

    /**
     * Get default environment variables: $_GET, $_POST, $_FILES, $_SERVER, $_COOKIE, method, route
     *
     * @return void
     */
    public static function defaultEnv()
    {
        // use X-HTTP-Method-Override to replace uncommon methods like DELETE/PUT to get through firewall
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $_SERVER['REQUEST_METHOD'] = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        }
        // PHP uses a non-standards compliant practice for $_GET, fix it here
        if (isset($_SERVER['QUERY_STRING'])) {
            $_GET = URI::parseQuery($_SERVER['QUERY_STRING']);
        }

        return [
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'server' => $_SERVER,
            'cookie' => $_COOKIE,
            'route' => static::$route,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ];
    }

    public static function request($route, $env = null)
    {
        if (is_null($env)) {
            $env = static::defaultEnv();
        }

        $router = static::router();
        $controller = $router->dispatch($env['method'], $route);
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
                    $file = "[$info->id] " . \Gini\File::relativePath($file, $info->path);
                    break;
                }
            }
            error_log(sprintf('ERROR %s', $message));
            $trace = array_slice($e->getTrace(), 0, 5);
            foreach ($trace as $n => $t) {
                $file = $t['file'] ?? '';
                foreach (\Gini\Core::$MODULE_INFO as $info) {
                    if (0 == strncmp($file, $info->path, strlen($info->path))) {
                        $file = "[$info->id] " . \Gini\File::relativePath($file, $info->path);
                        break;
                    }
                }
                error_log(sprintf(
                    '    %d) %s%s() in %s on line %d',
                    $n + 1,
                    $t['class'] ? $t['class'] . '::' : '',
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
            static::header('HTTP/1.1 500 Internal Server Error');
        }
    }

    public static function functionArguments($action, $params, $form = [])
    {
        $args = [];
        $r = is_string($action) ?
            new \ReflectionFunction($action) :
            new \ReflectionMethod($action[0], $action[1]);
        $rps = $r->getParameters();
        if (count($params) > 0 && is_numeric(key($params))) {
            // 需要考虑默认值以及无参数传入后使用func_get_args获取变量的情况
            $max = max(count($params), count($rps));
            for ($idx = 0; $idx < $max; $idx++) {
                $rp = $rps[$idx] ?? null;
                $args[] = $params[$idx] ??
                    ($rp
                        ? ($rp->isDefaultValueAvailable()
                            ? $rp->getDefaultValue() : null)
                        : null);
            }
        } elseif (count($rps) > 0) {
            // 如果是有字符串键值的, 尝试通过反射对应变量
            // 可以把form数据合并进去
            $params = array_merge((array) $form, (array) $params);
            // 修正变量名以配合驼峰式命名
            // user_id, user-id, userId
            $newParams = [];
            foreach ($params as $k => $v) {
                $key = strtolower(strtr($k, ['-' => '', '_' => '']));
                $newParams[$key] = $v;
            }
            foreach ($rps as $rp) {
                $key = strtolower(strtr($rp->name, ['-' => '', '_' => '']));
                $args[] = $newParams[$key] ?? ($rp->isDefaultValueAvailable() ? $rp->getDefaultValue() : null);
            }
        }
        return $args;
    }

    public static function executeAction($action, $params, $form = [])
    {
        $args = static::functionArguments($action, $params, $form);
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

    public static function redirect($url = '', $query = null, $permanent  = false)
    {
        $redirect = static::$requestOptions['redirect'];
        if ($redirect) {
            $redirect($url, $query, $permanent);
        } else {
            static::header('Location: ' . URL($url, $query), true, $permanent ? 301 : 302);
            exit();
        }
    }

    public static function header(string $string, bool $replace = true, int $http_response_code = 0)
    {
        $header = static::$requestOptions['header'];
        if ($header) {
            $header($string, $replace, $http_response_code);
        } else {
            header($string, $replace, $http_response_code);
        }
    }

    public static function router($nocache = true)
    {
        static $router;
        if (!$router && class_exists('\Gini\CGI\Router')) {
            // load router cache
            $router_cache_file = $_SERVER['GINI_APP_PATH'] . '/cache/router.json';
            if (!$nocache && file_exists($router_cache_file)) {
                $router_cache = @json_decode(@file_get_contents($router_cache_file), true);
                if ($router_cache) {
                    $router = \Gini\CGI\Router::fromJSON($router_cache);
                }
            } else {
                $router = new \Gini\CGI\Router();
                foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
                    $moduleClass = '\Gini\Module\\' . strtr($name, ['-' => '', '_' => '', '/' => '']);
                    if (!isset($info->error) && method_exists($moduleClass, 'cgiRoute')) {
                        call_user_func([$moduleClass, 'cgiRoute'], $router);
                    }
                }
            }
        }
        return $router;
    }

    protected static $setupOptions;
    public static function setup(array $options = [])
    {
        static::$setupOptions = $options;

        Session::setup();

        if (!isset($options['aio']) || !$options['aio']) {
            static::beforeRequest($options);
        }
    }

    public static function shutdown()
    {
        if (!isset(static::$setupOptions['aio']) || !static::$setupOptions['aio']) {
            static::afterRequest();
        }
        Session::shutdown();
    }

    protected static $requestOptions;
    public static function beforeRequest(array $options = [])
    {
        URI::setup();
        static::$route = trim($_SERVER['PATH_INFO'] ?? $_SERVER['ORIG_PATH_INFO'], '/');
        $contentType = explode(';', $_SERVER['CONTENT_TYPE'], 2)[0];
        if ($contentType == 'application/json') {
            $_POST = (array) @json_decode(self::content(), true);
        } elseif (
            $contentType == 'application/x-www-form-urlencoded'
            && !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])
        ) {
            $_POST = URI::parseQuery(self::content());
        }
        static::$requestOptions = $options;

        if (Config::get('session.autostart') !== false) {
            Session::open();
        }
    }

    public static function afterRequest(array $options = [])
    {
        Session::close();
    }
}
