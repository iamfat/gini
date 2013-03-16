<?php

namespace Controller\CGI {

	final class API extends \Controller\CGI {

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
			header('Content-Type: application/json');
			file_put_contents('php://output', $response);
		}

	}

}

