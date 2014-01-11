<?php

namespace Gini\PHPUnit\RPC {

    require_once __DIR__ . '/../gini.php';

    class General extends \Gini\PHPUnit\CLI {

        private function _rpcCall($method, $params) {
            return [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                    'id' => uniqid(),
                ];
        }

        private function _testCall($method, $params) {
            $apiData =  [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                    'id' => uniqid(),
                ];
                
            $response = \Model\API::dispatch($apiData);
            $this->assertSame($params[0], $response['result'], 'call '.$method);
        }
        
        public function testApiName() {

            $var = uniqid();

            $this->_testCall('RPCTest.camelCaseMethod', [$var]);
            $this->_testCall('rpctest.camelcasemethod', [$var]);
            $this->_testCall('rpctest.underscore_method', [$var]);
            $this->_testCall('rpctest/underscore_method', [$var]);            
        }
    
    }
        
}

namespace Controller\API {
    
    class RPCTest {
        
        function camelCaseMethod($s) {
            return $s;
        }
        
        function underscore_method($s) {
            return $s;
        }
        
    }
    
}