<?php

/**
 * REST Controller.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2016-09-29
 **/

/**
 * Define DocBlock.
 **/

namespace Gini\Controller;

abstract class REST extends CGI
{
    /**
     * Execute current action with parameters.
     */
    public function execute()
    {
        $method = $this->env['method'];
        $action = $this->action ?: 'default';
        $params = (array) $this->params;

        $response = $this->__preAction($action, $params);
        if ($response !== false) {
            set_error_handler(function () {}, E_ALL ^ E_NOTICE);
            $response = call_user_func_array(array($this, $method.$action), $params);
            restore_error_handler();
        }

        $response = $this->__postAction($action, $params, $response) ?: $response;

        return $response ?: new \Gini\CGI\Response\Nothing();
    }
}
