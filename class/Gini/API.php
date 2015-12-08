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
    public static function dispatch(array $data)
    {
        try {
            $id = $data['id'] ?: null;

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
                $callback = array($class, '__invoke');
            } else {
                $method = array_pop($path_arr);
                if (count($path_arr) > 0) {
                    $class = '\Gini\Controller\API\\'.implode('\\', $path_arr);
                } else {
                    $class = '\Gini\Controller\API';
                }

                if (class_exists($class) && $method[0] != '_') {
                    $method = 'action'.$method;
                    $o = \Gini\IoC::construct($class);
                    if (method_exists($o, $method)) {
                        $callback = array($o, $method);
                    } elseif (function_exists($class.'\\'.$method)) {
                        $callback = $class.'\\'.$method;
                    }
                }
            }

            if (!is_callable($callback)) {
                throw new API\Exception('Method not found', -32601);
            }

            $result = call_user_func_array($callback, $params);

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
