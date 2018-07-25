<?php

/**
 * JSON-RPC API support.
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

class API
{
    public static function dispatch(array $data, $env = [])
    {
        try {
            $id = $data['id'] ?: null;
            $env = $env ?: \Gini\CGI::defaultEnv();

            if (!isset($data['method'])
                || !isset($data['params']) || !isset($data['jsonrpc'])
                || !$data['method']
                || $data['jsonrpc'] != '2.0') {
                throw new API\Exception('Invalid Request', -32600);
            }

            $path = strtolower(strtr($data['method'], ['.' => '/', '::' => '/']));
            $params = $data['params'];

            $path_arr = explode('/', $path);
            $class = '\Gini\Controller\API\\'.implode('\\', $path_arr);

            if (class_exists($class) && method_exists($class, '__invoke')) {
                // might not be necessary, since __invoke is the magic method since PHP 5.3
                $o = \Gini\IoC::construct($class);
                $o->app = \Gini\Core::app();
                $o->env = $env;
                $callback = [$o, '__invoke'];
            } else {
                $method = array_pop($path_arr);
                if (count($path_arr) > 0) {
                    $class = '\Gini\Controller\API\\'.implode('\\', $path_arr);
                } else {
                    $class = '\Gini\Controller\API\Index';
                }

                if (class_exists($class) && $method[0] != '_') {
                    $method = 'action'.$method;
                    $o = \Gini\IoC::construct($class);
                    $o->app = \Gini\Core::app();
                    $o->env = $env;
                    if (method_exists($o, $method)) {
                        $callback = [$o, $method];
                    } elseif (function_exists($class.'\\'.$method)) {
                        $callback = $class.'\\'.$method;
                    }
                }
            }

            if (!is_callable($callback)) {
                throw new API\Exception('Method not found', -32601);
            }

            $args = \Gini\CGI::functionArguments($callback, $params);
            $result = call_user_func_array($callback, $args);

            if ($id !== null) {
                $response = [
                    'jsonrpc' => '2.0',
                    'result' => $result,
                    'id' => $id,
                ];
            }
        } catch (API\Exception $e) {
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
