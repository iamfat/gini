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
            if (is_callable($assertion)) {
                $assertion = $assertion();
            }

            if (!$assertion) {
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
