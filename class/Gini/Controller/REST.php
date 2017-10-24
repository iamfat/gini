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

use \Gini\CGI\Response;

abstract class REST extends CGI
{
    /**
     * Execute current action with parameters.
     */
    public function execute()
    {
        $method = strtolower($this->env['method'] ?: 'get');
        $params = (array) $this->params;

        try {
            if ($this->action) {
                $actionName = preg_replace('/^(get|post|put|delete|options)/i', '', $this->action);
            } else {
                $actionName = strtr($params[0], ['-' => '', '_' => '']);
                if ($actionName && $actionName[0] != '_'
                    && method_exists($this, $method.$actionName)) {
                    array_shift($params);
                } else {
                    $actionName = 'default';
                    if (!method_exists($this, $method.$actionName)) {
                        throw new Response\Exception(null, 404);
                    }
                }
                $this->action = $method . $actionName;
            }

            $response = $this->__preAction($actionName, $params);
            if ($response !== false) {
                $response = \Gini\CGI::executeAction([$this, $this->action], $params, $this->form());
            }
            $response = $this->__postAction($actionName, $params, $response) ?: $response;
        } catch (Response\Exception $e) {
            $response = new Response\JSON(['error'=>[
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ]], $e->getCode());
        }

        return $response ?: new Response\Nothing();
    }
}
