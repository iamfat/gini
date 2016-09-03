<?php

namespace Gini\Controller\CGI;

final class API extends \Gini\Controller\CGI
{
    public function __index() {}

    public function execute()
    {
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
        } else {
            $response = \Gini\API::dispatch((array) $request);
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $response);
    }
}
