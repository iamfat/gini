<?php

namespace Controller\CGI;

class Index extends Layout
{
    public function __index()
    {
        $this->view->title = 'Gini PHP Framework';
        $this->view->body = V('body');
    }

}
