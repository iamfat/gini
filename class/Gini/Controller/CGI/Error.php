<?php

namespace Gini\Controller\CGI;

class Error extends Layout
{
    public function __index($code = 404)
    {
        http_response_code($code);
        if ($_SERVER['HTTP_X_REQUESTED_WITH']) {
            return false;
        }

        $this->view->body = V('error', ['code' => $code]);
    }
}
