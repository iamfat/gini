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
     * Environmental variables such as $_GET/$_POST/$_FILES/route passed to current controller.
     *
     * @var array
     */
    public $env;

    /**
     * route to current controller.
     *
     * @var string
     **/
    public $route;

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

    /**
     * Execute current action with parameters.
     */
    public function execute()
    {
        $action = $this->action ? 'action'.$this->action : '__index';
        $params = (array) $this->params;

        $response = $this->__preAction($action, $params);
        if ($response !== false) {
            $response = call_user_func_array(array($this, $action), $params);
        }

        $response = $this->__postAction($action, $params, $response) ?: $response;

        return $response ?: new \Gini\CGI\Response\Nothing();
    }

    /**
     * Return POST/GET/FILES in array.
     *
     * @param string $mode 'get': $_GET, 'post': $_POST, 'files': $_FILES, default: $_POST + $_GET
     *
     * @return array Return corresponding form data
     */
    public function form($mode = '*')
    {
        switch ($mode) {
        case 'get':
            return $this->env['get'] ?: [];
        case 'post':
            return $this->env['post'] ?: [];
        case 'files':
            return $this->env['files'] ?: [];
        default:
            return array_merge((array) $this->env['get'], (array) $this->env['post']);
        }
    }

    /**
     * Redirect to other path.
     *
     * @param string            $url   URL to redirect
     * @param array|string|null $query Query parameters need to include in URL
     */
    public function redirect($url = '', $query = null)
    {
        // session_write_close();
        header('Location: '.URL($url, $query), true, 302);
        exit();
    }
}
