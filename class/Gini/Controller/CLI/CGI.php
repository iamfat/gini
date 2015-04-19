<?php

namespace Gini\Controller\CLI;

class CGI extends \Gini\Controller\CLI
{
    public function actionRequest($args)
    {
        if (count($args) < 1) {
            die("Usage: gini cgi request <URL>\n");
        }
        $route = array_shift($args);

        array_map(function ($arg) {
            list($k, $v) = explode('=', $arg);
            $_GET[$k] = $v;
        }, $args);

        $env = ['get' => $_GET, 'post' => $_POST, 'files' => $_FILES, 'route' => $route];

        echo "Requesting {$route}...\n";
        $content = \Gini\CGI::request($route, $env)->execute()->content();
        if (is_string($content)) {
            echo $content;
        } else {
            echo yaml_emit($content);
        }
    }
}
