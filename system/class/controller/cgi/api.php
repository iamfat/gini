<?php

namespace Controller\CGI {

	final class API extends \Controller\CGI {

		function __index() {
			$response = \Model\API::dispatch((array)\Model\CGI::JSON());
			return new \Model\CGI\Response\JSON($response);
		}

	}

}

