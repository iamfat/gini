<?php

/**
 * CGI Controller.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2014-02-08
 **/

/**
 * Define DocBlock.
 **/

namespace Gini\Controller;

use Gini\CGI\Response;

abstract class CGI
{
    /**
     * Current action.
     *
     * @var string
     */
    public $action;

    /**
     * Parameters passed to current action.
     *
     * @var array
     */
    public $params;

    /**
     * current app module info.
     *
     * @var array
     */
    public $app;

    /**
     * Environmental variables such as $_GET/$_POST/$_FILES/route passed to current controller.
     *
     * @var array
     */
    public $env;

    /**
     * contains all middlware instances for current controller
     *
     * @var array
     */
    public $middlewares = [];

    /**
     * Function called right before action is being executed.
     *
     * @param string $action
     * @param string $params
     */
    protected function __preAction($action, &$params)
    {
    }

    /**
     * Function called right after action was executed.
     *
     * @param string $action
     * @param string $params
     * @param string $response
     */
    protected function __postAction($action, &$params, $response)
    {
    }

    protected function normalizeActionParams()
    {
        $params = (array) $this->params;
        $methodName = $this->action;
        if ($methodName) {
            $actionName = preg_replace('/^action/i', '', $methodName);
        } else {
            $actionName = strtr($params[0], ['-' => '', '_' => '']);
            if (
                $actionName && $actionName[0] != '_'
                && method_exists($this, 'action' . $actionName)
            ) {
                array_shift($params);
            } elseif (method_exists($this, '__index')) {
                $actionName = null;
            } else {
                throw new Response\Exception(null, 404);
            }
            $methodName = $actionName ? 'action' . $actionName : '__index';
        }

        return [$actionName, $methodName, $params];
    }

    public function errorResponse($e)
    {
        return new Response\HTML(V('error/http', [
            'code' => $e->getCode(),
            'message' => $e->getMessage()
        ]), $e->getCode());
    }

    /**
     * Execute current action with parameters.
     */
    public function execute()
    {
        try {
            list($actionName, $methodName, $params) = $this->normalizeActionParams();

            // 1. pass through all middlewares
            if (is_array($this->middlewares)) {
                foreach ($this->middlewares as $middleware) {
                    if (is_string($middleware)) {
                        $middleware = \Gini\CGI\Middleware::of($middleware);
                    }
                    $middleware->process($this, $actionName, $params);
                }
            }

            // 2. preAction/action/postAction
            $response = $this->__preAction($actionName, $params);
            if ($response !== false) {
                $response = \Gini\CGI::executeAction([$this, $methodName], $params, $this->form());
            }
            $response = $this->__postAction($actionName, $params, $response) ?: $response;
        } catch (Response\Exception $e) {
            $response = $this->errorResponse($e);
        }

        return $response ?: new Response\Nothing();
    }

    /**
     * Return POST/GET/FILES or JSON content in array.
     *
     * @param string $mode 'get': $_GET, 'post': $_POST, 'files': $_FILES, default: $_POST + $_GET
     *
     * @return array Return corresponding form data
     */
    public function form($mode = '*', $data = null, $overwrite = false)
    {
        if ($data === null) {
            switch ($mode) {
                case 'get':
                    return $this->env['get'] ?? [];
                case 'post':
                    return $this->env['post'] ?? [];
                case 'files':
                    return $this->env['files'] ?? [];
                default:
                    return array_merge($this->env['get'] ?? [], $this->env['post'] ?? []);
            }
        } else {
            switch ($mode) {
                case 'get':
                    $this->env['get'] = $this->env['get'] ?? [];
                    if ($overwrite) {
                        $this->env['get'] = $data;
                    } else {
                        array_merge($this->env['get'], $data);
                    }
                case 'post':
                    $this->env['post'] = $this->env['post'] ?? [];
                    if ($overwrite) {
                        $this->env['post'] = $data;
                    } else {
                        array_merge($this->env['post'], $data);
                    }
            }
        }
    }

    /**
     * Redirect to other path.
     *
     * @param string            $url   URL to redirect
     * @param array|string|null $query Query parameters need to include in URL
     */
    public function redirect()
    {
        $args = func_get_args();
        call_user_func_array('\Gini\CGI::redirect', $args);
    }

    public function header()
    {
        $args = func_get_args();
        call_user_func_array('\Gini\CGI::header', $args);
    }
}
