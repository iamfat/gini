<?php

namespace Model {

    class CGI {

        static function stripslashes(& $value)
        {
            return is_array($value) ?
                    array_map([__CLASS__, __FUNCTION__], $value) :
                    stripslashes($value);
        }

        static $dispatchers = [];
        static function set_dispatcher($mime, $dispatcher) {
            $dispatchers[$mime] = $dispatcher;
        }

        static function main($argv) {
            $accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
            while (null !== ($accept = array_pop($accepts))) {
                list($mime,) = explode(';', $accept, 2);
                $dispatcher = $dispatchers[$mime];
                if ($dispatcher) {
                    return call_user_func($dispatcher, $argv);
                }
            }

            return self::default_dispatcher($argv);    
        }

        static function default_dispatcher($argv) {
            
            //从末端开始尝试
            /*
                home/page/edit/1/2

                home/page/index.php Controller\Page\Index::edit(1)
                home/page/index.php Controller\Index::index('edit', 1)
                home/page.php        Controller\Page::edit(1)
                home/page.php        Controller\Page::index('edit', 1)
            */

            $args = (array) self::args();
            $path = '';
            $candidates = array('index' => $args);
            while (count($args) > 0) {
                $arg = array_shift($args);
                if (!preg_match('|^[a-z-_][\w-]*$|', $arg)) break;
                $arg = strtolower(strtr($arg, '-', '_'));
                if ($path) $path .= '/' . $arg;
                else $path = $arg;
                $candidates[$path] = $args;
            } 

            $class = null;
            foreach(array_reverse($candidates) as $path => $params){
                $basename = basename($path);
                $dirname = dirname($path);
                $class_namespace = '\\Controller\\CGI\\';
                if ($dirname != '.') {
                    $class_namespace .= str_replace('/', '_', ucwords($dirname)).'\\';
                }
                $class = $class_namespace . ucwords($basename);
                if (class_exists($class)) break;
                $class = $class_namespace . 'Controller_' . ucwords($basename);
                if (class_exists($class)) break;
            }

            if (!$class || !class_exists($class, false)) URI::redirect('error/404');

            _CONF('runtime.controller_path', $path);
            _CONF('runtime.controller_class', $class);
            $controller = new $class;

            $action = strtr($params[0], '-', '_');
            if ($action && $action[0]!='_' && method_exists($controller, 'action_'.$action)) {
                $action = 'action_'.$action;
                array_shift($params);
            }
            elseif (method_exists($controller, '__index')) {
                $action = '__index';
            }
            else {
                self::redirect('error/404');
            }

            $controller->action($action, $params);
        }

        static function exception($e) {
            $message = $e->getMessage();
            if ($message) {
                $file = \Model\File::relative_path($e->getFile());
                $line = $e->getLine();
                error_log(sprintf("\x1b[31m\x1b[4mERROR\x1b[0m \x1b[1m%s\x1b[0m", $message));
                $trace = array_slice($e->getTrace(), 1, 5);
                foreach ($trace as $n => $t) {
                    error_log(sprintf("    %d) %s%s() in %s on line %d", $n + 1,
                                    $t['class'] ? $t['class'].'::':'', 
                                    $t['function'],
                                    \Model\File::relative_path($t['file']),
                                    $t['line']));

                }
            }

            if (PHP_SAPI != 'cli') {
                while(@ob_end_clean());    //清空之前的所有显示
                header('HTTP/1.1 500 Internal Server Error');
            }        
        }

        protected static $form, $files, $get, $post;
        protected static $args, $route;

        static function setup(){

            URI::setup();

            self::$route = $route = ltrim($_SERVER['PATH_INFO'] ?: $_SERVER['ORIG_PATH_INFO'], '/');

            $args = array();
            if(preg_match_all('|(.*?[^\\\])\/|', $route.'/', $parts)){
                foreach($parts[1] as $part) {
                    $args[] = strtr($part, array('\/'=>'/'));
                }
            }

            self::$args = $args;
        }

        static function & form($mode = '*') {

            if (!isset(self::$get)) {
                self::$get = $_GET;
                self::$post = $_POST;
                self::$files = $_FILES;
                self::$form = array_merge($_POST, $_GET);
            }

            switch($mode) {
            case 'get':
                return self::$get;
            case 'post':
                return self::$post;
            default:
                return self::$form;
            }
        }

        static function content() {
            return file_get_contents('php://input');
        }

        static function & route($route = null) {
            if (is_null($route)) {
                return self::$route;
            }
            self::$route = $route;
        }

        static function & args() {
            return self::$args;
        }

        static function & files() {
            return self::$files;
        }

        static function redirect($url='', $query=null) {
            // session_write_close();
            header('Location: '. URL($url, $query), true, 302);
            exit();
        }
        
        static function shutdown() { 
        }
    }
    
}

namespace Model\CGI {
    interface Response {
        function output();
    }
}

namespace {

    function H(){
        $args = func_get_args();
        if (count($args) > 1) {
            $str = call_user_func_array('sprintf', $args);
        }
        else {
            $str = $args[0];
        }
        return htmlentities(iconv('UTF-8', 'UTF-8//IGNORE', $str), ENT_QUOTES, 'UTF-8');
    }

    function s() {
        $args = func_get_args();
        if (count($args) > 1) {
            call_user_func_array('sprintf', $args);
        }
        else {
            return $args[0];
        }
    }    

}
