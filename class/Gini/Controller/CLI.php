<?php

/**
 * CLI Controller
 *
 * @author Jia Huang
 * @version $Id$
 * @copyright Genee, 2014-02-08
 **/

/**
 * Define DocBlock
 **/

namespace Gini\Controller;

abstract class CLI
{
    /**
     * current action
     *
     * @var string
     */
    public $action;

    /**
     * current parameters
     *
     * @var array
     */
    public $params;

    protected function __preAction($action, &$params) { }

    protected function __postAction($action, &$params, $response) { }

    public function execute()
    {
        $action = $this->action ?: '__index';
        $params = (array) $this->params;

        $this->__preAction($action, $params);
        $response = call_user_func(array($this, $action), $params);

        return $this->__postAction($action, $params, $response) ?: $response;
    }

    public function __index($params)
    {
        $this->__unknown($params);
    }

    public function actionHelp($params)
    {
        echo "\e[1;34mgini\e[0m: help is unavailable.\n";
    }

    public function __unknown($params)
    {
        echo "\e[1;34mgini\e[0m: unknown command.\n";
    }

}
