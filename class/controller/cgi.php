<?php

/**
 * CGI Controller
 *
 * @author Jia Huang
 * @version $Id$
 * @copyright Genee, 2014-02-08
 **/

/**
 * Define DocBlock
 **/

namespace Controller;

abstract class CGI
{
    /**
     * Current action
     *
     * @var string
     */
    public $action;

    /**
     * Parameters passed to current action
     *
     * @var array
     */
    public $params;

    /**
     * GET/POST/FILES passed to current controller
     *
     * @var array
     */
    public $form;

    /**
     * Function called right before action is being executed
     *
     * @param  string $action
     * @param  string $params
     * @return void
     */
    protected function __preAction($action, &$params) { }

    /**
     * Function called right after action was executed
     *
     * @param  string $action
     * @param  string $params
     * @param  string $response
     * @return void
     */
    protected function __postAction($action, &$params, $response) { }

    /**
     * Execute current action with parameters
     *
     * @return void
     */
    public function execute()
    {
        $action = $this->action ?: '__index';
        $params = (array) $this->params;

        $this->__preAction($action, $params);
        $response = call_user_func_array(array($this, $action), $params);

        return $this->__postAction($action, $params, $response) ?: $response;
    }

    /**
     * Return POST/GET/FILES in array
     *
     * @param  string $mode 'get': $_GET, 'post': $_POST, 'files': $_FILES, default: $_POST + $_GET
     * @return array  Return corresponding form data
     */
    public function form($mode = '*')
    {
        switch ($mode) {
        case 'get':
            return $this->form['get'] ?: [];
        case 'post':
            return $this->form['post'] ?: [];
        case 'files':
            return $this->form['files'] ?: [];
        default:
            return array_merge((array) $this->form['get'], (array) $this->form['post']);
        }
    }

    /**
     * Redirect to other path
     *
     * @param  string            $url   URL to redirect
     * @param  array|string|null $query Query parameters need to include in URL
     * @return void
     */
    public function redirect($url='', $query=null)
    {
        // session_write_close();
        header('Location: '. URL($url, $query), true, 302);
        exit();
    }

}
