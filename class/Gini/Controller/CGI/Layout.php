<?php

namespace Gini\Controller\CGI;

class Layout extends \Gini\Controller\CGI
{
    public $view;
    protected static $layout_name = 'layout';

    public function __preAction($action, &$params)
    {
        parent::__preAction($action, $params);
        $this->view = V(static::$layout_name);
        $this->view->title = \Gini\Config::get('layout.title');
    }

    public function __postAction($action, &$params, $response)
    {
        parent::__postAction($action, $params, $response);
        if (null === $response) $response = \Gini\IoC::construct('\Gini\CGI\Response\HTML', $this->view);
        return $response;
    }

}
