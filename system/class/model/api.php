<?php

namespace Model;

class API_Exception extends \Exception {}

class API {

	private static $_debug = FALSE;
	static function debug($debug=TRUE) {
		self::$_debug = $debug;
	}

	static function dispatch($json_str) {
		// 首先解析body中的json格式
		$data = @json_decode($json_str, TRUE);

		try {
		
			$path = strtolower($data['method']);
			$params = $data['params'];
			$id =  $data['id']?: mt_rand();
			
			if ($data['jsonrpc'] != '2.0') throw new API_Exception('This is not an JSON-RPC 2.0 request!');
			
			if (!$path) throw new API_Exception('Method must not be empty!');
		
			$path_arr = explode('/', $path);
			$class = '\\API\\'.implode('\\', $path_arr);

			if (class_exists($class) && method_exists($class, '__invoke')) {
				// might not be necessary, since __invoke is the magic method since PHP 5.3
				$callback = array($class, '__invoke');
			}
			else {
				$method = array_pop($path_arr);
				$path = implode('/', $path_arr);
				if (count($path_arr) > 0) {
					$class = '\\API\\' . implode('\\', $path_arr);
				}
				else {
					$class = '\\API';
				}

				if ($method[0] != '_') {
					class_exists($class);
					if (method_exists($class, $method)) {
						$callback = array($class, $method);
					}	
					elseif (function_exists($class . '\\' . $method)) {
						$callback = $class . '\\' . $method;
					}
				} 

			}

			if (!is_callable($callback)) {
				throw new API_Exception("Method not exists!");
			}
		
			if (self::$_debug) {
				$func_str = trim(var_export($callback, TRUE), '\'');
				$params_str = preg_replace('/\[(.*)\]/', '$1', @json_encode($params));
				TRACE( '<<< '.$func_str. '('. $params_str.')');
			}
		
			$result = call_user_func_array($callback, $params);
		
			$response = array(
				'jsonrpc' => '2.0', 
				'result' => $result,
				'id' => $id,
			);
		
			if (self::$_debug) {
				TRACE('>>> '.@json_encode($response));
			}
			
		}
		catch (API_Exception $e) {
			TRACE($e->getMessage());
			$response = array(
				'jsonrpc' => '2.0', 
				'error' => array(
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
					),
				'id' => $id,
			);

		}
		
		return @json_encode($response) . "\n";
	}

}

