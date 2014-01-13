<?php

namespace Controller\CGI {

    final class API extends \Controller\CGI {

        function __index() {
            $request = @json_decode(\Gini\CGI::content(), true);
            if ($request === null) {
                $response = [
                    'jsonrpc' => '2.0', 
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error',
                    ],
                    'id' => $id,
                ];    
            }
            else {
                 $response = \Gini\API::dispatch((array)$request);
            }            
            return new \Gini\CGI\Response\JSON($response);
        }

    }

}

