<?php

namespace Gini\CGI\Response;

class Nothing implements \Gini\CGI\Response
{
    public function __construct()
    {
    }

    public function output($res = null)
    {
    }

    public function content()
    {
    }
}
