<?php

namespace Controller\API {

	function subtract($a, $b) {
		return $a - $b;
	}

}

namespace Test\Unit\System {
	
	class API extends \Model\Test\Unit {
	
		function setup() {

		}
		
		private function call($request) {
			return \Model\API::dispatch($request);
		}
	
		function test_api() {
			// 根据 http://www.jsonrpc.org/specification 的范例, 进行测试
			$response = $this->call(["jsonrpc"=>"2.0", "method"=>"subtract", "params"=>[42, 23], "id"=>1 ]);	
			$this->assert('subtract(42, 23) == 19', $response == ["jsonrpc"=>"2.0","result"=>19,"id"=>1]);

			$response = $this->call(["jsonrpc"=>"2.0", "method"=>"subtract", "params"=>[23, 42], "id"=>1 ]);	
			$this->assert('subtract(23, 42) == -19', $response == ["jsonrpc"=>"2.0","result"=>-19,"id"=>2]);
	
		}
	
		function teardown() {
	
		}
	
	}
	
}

