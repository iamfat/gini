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

use Gini\CGI\Response;

abstract class REST extends CGI
{
    protected function normalizeActionParams()
    {
        $method = strtolower($this->env['method'] ?? 'get');
        $params = (array) $this->params;
        $methodName = $this->action;

        if ($methodName) {
            $actionName = preg_replace('/^(get|post|put|delete|options|patch)/i', '', $this->action);
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
            $methodName = $method . $actionName;
        }

        return [$actionName, $methodName, $params];
    }

    public function errorResponse($e)
    {
        return new Response\JSON(['error'=>[
            'code' => $e->getCode(),
            'message' => $e->getMessage()
        ]], $e->getCode());
    }
}
