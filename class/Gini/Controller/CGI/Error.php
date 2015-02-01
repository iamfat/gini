<?php

namespace Gini\Controller\CGI;

class Error extends Layout
{
    public function __index($code = 404)
    {
        switch ($code) {
        case 401:
            $title = "Unauthorized visit";
            header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
            header("Status: 401 Unauthorized");
            break;
        case 404:
            $title = "File not found";
            header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
            header("Status: 404 Not Found");
            break;
        }

        if ($_SERVER['HTTP_X_REQUESTED_WITH']) {
            return false;
        }

        $this->view->title = $title;
        $this->view->body = V('error', ['code' => $code]);
    }
}
