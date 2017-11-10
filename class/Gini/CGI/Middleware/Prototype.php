<?php

namespace Gini\CGI\Middleware;

interface Prototype {
    function process($controller, $action, $params);
}