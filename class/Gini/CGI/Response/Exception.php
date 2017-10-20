<?php

namespace Gini\CGI\Response;

class Exception extends \Exception
{
    private $_HTTP_MESSAGES = [
        400 => 'Bad Request',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
    ];

    public function __construct($message, $code=500)
    {
        if ($message ===  null) {
            if (isset($this->_HTTP_MESSAGES[$code])) {
                $message = $this->_HTTP_MESSAGES[$code];
            } else {
                $message = 'Unknown Error';
            }
        }

        parent::__construct($message, $code);
    }
}
