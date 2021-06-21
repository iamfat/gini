<?php

namespace Gini\CGI;

interface Response
{
    public function output($res = null);
    public function content();
}
