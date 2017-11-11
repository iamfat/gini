<?php

namespace Gini\CGI\Middleware;

interface Prototype
{
    public function process($controller, $action, $params);
}
