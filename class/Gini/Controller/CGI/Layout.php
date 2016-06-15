<?php

namespace Gini\Controller\CGI;

abstract class Layout extends \Gini\Controller\CGI
{
    public $view;
    protected static $layout_name = 'layout';

    protected function __preAction($action, &$params)
    {
        parent::__preAction($action, $params);
        $this->view = V(static::$layout_name);
        $this->view->title = \Gini\Config::get('layout.title');
    }

    protected function __postAction($action, &$params, $response)
    {
        parent::__postAction($action, $params, $response);
        if (null === $response) {
            $response = \Gini\IoC::construct('\Gini\CGI\Response\HTML', $this->view);
        }

        return $response;
    }
}
