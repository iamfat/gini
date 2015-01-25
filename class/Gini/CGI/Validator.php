<?php

namespace Gini\CGI\Validator {
    class Exception extends \ErrorException
    {
    }
}

namespace Gini\CGI {

    class Validator
    {
        private $_errors;

        public function errors()
        {
            return $this->_errors;
        }

        public function validate($key, $assertion, $message)
        {
            if (isset($this->_errors[$key])) {
                return $this;
            }

            if (is_object($assertion) && ($assertion instanceof \Closure)) {
                $assertion = $assertion();
            }

            if (!$assertion) {
                $this->_errors[$key] = $message;
            }

            return $this;
        }

        public function done()
        {
            if (count($this->_errors) > 0) {
                throw new Validator\Exception();
            }
        }
    }

}
