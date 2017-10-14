<?php

namespace Gini\REST;

class Exception extends \Exception
{
    private $_data;

    public function __construct($message='', $code=400, $data='')
    {
        $this->_data = $data;
    }

    public function getData()
    {
        return $this->_data;
    }
}
