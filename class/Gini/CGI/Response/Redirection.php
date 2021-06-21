<?php

namespace Gini\CGI\Response;

use \Gini\CGI;

class Redirection extends \Gini\CGI\Response
{
    private $_url;
    private $_query;
    private $_code;

    public function __construct($url, $query, $code = 302)
    {
        parent::__construct(null, $code);
        $this->_url = $url;
        $this->_query = $query;
        $this->_code = $code;
    }

    public function output()
    {
        CGI::redirect($this->_url, $this->_query, $this->_code);
    }
}
