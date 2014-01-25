<?php

namespace Controller;

abstract class CGI {

    public $action;
    public $params;
    public $form;
    public $route;

    function __preAction($action, &$params) { }
    
    function __postAction($action, &$params, $response) { }

    function execute() {

        $action = $this->action ?: '__index';
        $params = (array) $this->params;

        $this->__preAction($action, $params);
        $response = call_user_func_array(array($this, $action), $params);
        return $this->__postAction($action, $params, $response) ?: $response;
    }
    
    function form($mode = '*') {
        switch($mode) {
        case 'get':
            return $this->form['get'] ?: [];
        case 'post':
            return $this->form['post'] ?: [];
        case 'files':
            return $this->form['files'] ?: [];
        default:
            return array_merge((array)$this->form['get'], (array)$this->form['post']);
        }
    }
    
    function redirect($url='', $query=null) {
        // session_write_close();
        header('Location: '. URL($url, $query), true, 302);
        exit();
    }
    
}
