<?php

namespace Gini\CGI\Response;

class RedirectException extends \Exception
{
    private $_url;
    private $_query;
    private $_code;

    public function __construct($url, $query, $code=302)
    {
        $this->_url = $url;
        $this->_query = $query;
        $this->_code = $code;
        parent::__construct("Redirect to $url", $code);
    }

    public function getResponse()
    {
        return new Redirection($this->_url, $this->_query, $this->_code);
    }
}
