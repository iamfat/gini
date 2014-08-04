<?php

namespace Gini\CGI\Validator {
    class Exception extends \ErrorException {}
}

namespace Gini\CGI {

    class Validator
    {
        private $_errors;

        function errors()
        {
            return $this->_errors;
        }

        function validate($key, $assertion, $message)
        {
            if (is_object($assertion) && ($assertion instanceof \Closure)) {
                $assertion = $assertion();
            }

            if (!$assertion && !isset($this->_errors[$key])) {
                $this->_errors[$key] = $message;
            }

            return $this;
        }

        function done()
        {
            if (count($this->_errors) > 0) {
                throw new Validator\Exception;
            }
        }
    }

}
