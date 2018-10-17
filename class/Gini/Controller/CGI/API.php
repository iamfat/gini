<?php

namespace Gini\Controller\CGI;

final class API extends \Gini\Controller\CGI
{
    public function __index()
    {
    }

    public function execute()
    {
        $post = $this->form('post');
        // try to parse from php://input for backward compatibility
        if (!isset($post['jsonrpc'])) {
            $post = @json_decode(\Gini\CGI::content(), true);
        }
        if (!isset($post['jsonrpc'])) {
            $post = @json_decode($this->env['raw'], true);
        }

        if ($post === null) {
            $response = [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error',
                ],
                'id' => $id,
            ];
        } else {
            $response = \Gini\API::dispatch((array)$post, $this->env);
        }

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $response);
    }
}
