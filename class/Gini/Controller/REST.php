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
        $params = (array) $this->params;

        if ($this->action) {
            $actionName = preg_replace('/^(get|post|put|delete|options)/i', '', $this->action);
        } else {
            $actionName = strtr($params[0], ['-' => '', '_' => '']);
            if ($actionName && $actionName[0] != '_'
                && method_exists($this, $method.$actionName)) {
                array_shift($params);
            } else {
                $actionName = 'default';
            }
            $this->action = $method . $actionName;
        }

        $response = $this->__preAction($actionName, $params);
        if ($response !== false) {
            set_error_handler(function () {
            }, E_ALL ^ E_NOTICE);
            $response = \Gini\CGI::executeAction([$this, $this->action], $params, $this->form());
            restore_error_handler();
        }

        $response = $this->__postAction($actionName, $params, $response) ?: $response;

        return $response ?: new \Gini\CGI\Response\Nothing();
    }
}
