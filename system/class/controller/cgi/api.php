<?php

namespace Controller;

final class API extends \Model\Controller {

	function __index() {
		$request = file_get_contents('php://input');

		/*
		if (!$request) {
			$data = $_POST;
			if (!is_array($data['params'])) {
				$data['params'] = (array) @json_decode((string)$data['params']);
			}
			$request = @json_encode($data);
		}
		*/
		
		$response = \Model\API::dispatch($request);
		file_put_contents('php://output', $response);
	}

}