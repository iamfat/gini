<?php

namespace Gini\PHPUnit\RPC {

    require_once __DIR__.'/../gini.php';

    class General extends \Gini\PHPUnit\CLI
    {
       private function _testCall($method, $params)
        {
            $apiData =  [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                    'id' => uniqid(),
                ];

            return \Gini\API::dispatch($apiData);
        }

        public function testApiName()
        {
            $var = uniqid();

            $response = $this->_testCall('RPCTest.camelCaseMethod', [$var]);
            $this->assertSame($var, $response['result'], 'call RPCTest.camelCaseMethod');

            $this->_testCall('rpctest.camelcasemethod', [$var]);
            $this->assertSame($var, $response['result'], 'call rpctest.camelcasemethod');

            $this->_testCall('rpctest/camelCaseMethod', [$var]);
            $this->assertSame($var, $response['result'], 'rpctest/camelCaseMethod');
        }

        public function testNamedParameters()
        {
            $var = uniqid();

            $response = $this->_testCall('RPCTest.echo', ['a' => 1, 'b' => 2, 'c' => 3]);
            $this->assertSame($response['result'], ['a' => 1, 'b' => 2, 'c' => 3]);

            $response = $this->_testCall('RPCTest.echo', ['b' => 2, 'a' => 1, 'c'=> 3]);
            $this->assertSame($response['result'], ['a' => 1, 'b' => 2, 'c' => 3]);
        }

        public function testUnnamedParameters()
        {
            $var = uniqid();

            $response = $this->_testCall('RPCTest.echo', [1]);
            $this->assertSame($response['result'], ['a' => 1, 'b' => null, 'c' => 2]);
        }
    }

}

namespace Gini\Controller\API {

    class RPCTest
    {
        public function actionCamelCaseMethod($s)
        {
            return $s;
        }

        public function actionEcho($a, $b, $c=2) {
            return ['a' => $a, 'b' => $b, 'c' => $c];
        }
    }

}
