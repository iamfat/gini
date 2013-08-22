<?php

namespace Model {

    class CGI {

        static function stripslashes(& $value)
        {
            return is_array($value) ?
                    array_map([__CLASS__, __FUNCTION__], $value) :
                    stripslashes($value);
        }

        static function main(& $argv) {
            
            //从末端开始尝试
            /*
                home/page/edit/1/2

                home/page/index.php Controller\Page\Index::edit(1)
                home/page/index.php Controller\Index::index('edit', 1)
                home/page.php        Controller\Page::edit(1)
                home/page.php        Controller\Page::index('edit', 1)
            */

            $response = self::request(self::$route, ['get'=>$_GET, 'post'=>$_POST, 'files'=>$_FILES])->execute();
            if ($response) $response->output();
        }
        
        static function request($route, array $form = array()) {
            
            $args = explode('/', $route);

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
                if ($basename != 'index') {
                    $class = $class_namespace . 'Index';
                    if (class_exists($class)) break;
                }
            }

            if (!$class || !class_exists($class, false)) self::redirect('error/404');

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

            $controller->action = $action;
            $controller->params = $params;
            $controller->form = $form;

            return $controller;     
        }

        static function exception($e) {
            $message = $e->getMessage();
            if ($message) {
                $file = File::relative_path($e->getFile());
                $line = $e->getLine();
                error_log(sprintf("\e[31m\e[4mERROR\e[0m \e[1m%s\e[0m", $message));
                $trace = array_slice($e->getTrace(), 1, 5);
                foreach ($trace as $n => $t) {
                    error_log(sprintf("    %d) %s%s() in %s on line %d", $n + 1,
                                    $t['class'] ? $t['class'].'::':'', 
                                    $t['function'],
                                    File::relative_path($t['file']),
                                    $t['line']));

                }
            }

            if (PHP_SAPI != 'cli') {
                while(@ob_end_clean());    //清空之前的所有显示
                header('HTTP/1.1 500 Internal Server Error');
            }        
        }

        protected static $route;

        static function setup(){
            URI::setup();
            self::$route = trim($_SERVER['PATH_INFO'] ?: $_SERVER['ORIG_PATH_INFO'], '/');
        }

        static function content() {
            return file_get_contents('php://input');
        }

        static function route($route = null) {
            if (is_null($route)) {
                return self::$route;
            }
            self::$route = $route;
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
        function content();
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
