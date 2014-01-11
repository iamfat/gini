<?php

namespace Model\API {
    class Exception extends \Exception {}
}

namespace Model {

    class API {
    
        private static $_debug = false;
        static function debug($debug = true) {
            self::$_debug = $debug;
        }
    
        static function dispatch(array $data) {
    
            try {
                
                $id =  $data['id'] ?: null;
                
                if (!isset($data['method']) 
                    || !isset($data['params']) || !isset($data['jsonrpc'])
                    || !$data['method']
                    || $data['jsonrpc'] != '2.0') throw new API\Exception('Invalid Request', -32600);

                $path = strtolower(strtr($data['method'], ['.'=>'/', '::'=>'/']));
                $params = $data['params'];
                
                $path_arr = explode('/', $path);
                $class = '\\Controller\\API\\'.implode('\\', $path_arr);
    
                if (class_exists($class) && method_exists($class, '__invoke')) {
                    // might not be necessary, since __invoke is the magic method since PHP 5.3
                    $callback = array($class, '__invoke');
                }
                else {
                    $method = array_pop($path_arr);
                    if (count($path_arr) > 0) {
                        $class = '\\Controller\\API\\' . implode('\\', $path_arr);
                    }
                    else {
                        $class = '\\Controller\\API';
                    }
    
                    if ($method[0] != '_') {
                        $o = new $class;
                        if (method_exists($o, $method)) {
                            $callback = array($o, $method);
                        }    
                        elseif (function_exists($class . '\\' . $method)) {
                            $callback = $class . '\\' . $method;
                        }
                    } 
    
                }
    
                if (!is_callable($callback)) {
                    throw new API\Exception("Method not found", -32601);
                }
            
                if (self::$_debug) {
                    $func_str = trim(var_export($callback, true), '\'');
                    $params_str = preg_replace('/\[(.*)\]/', '$1', @json_encode($params));
                    TRACE( '<<< '.$func_str. '('. $params_str.')');
                }
            
                $result = call_user_func_array($callback, $params);
            
                if ($id !== null) {
                    $response = [
                        'jsonrpc' => '2.0', 
                        'result' => $result,
                        'id' => $id,
                    ];
            
                    if (self::$_debug) {
                        TRACE('>>> '.@json_encode($response));
                    }                
                }
            
            }
            catch (API\Exception $e) {
                $response = [
                    'jsonrpc' => '2.0', 
                    'error' => [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ],
                    'id' => $id,
                ];    
            }
            
            return $response;
        }
    
    }
    
}