<?php

namespace Model\Validator {
    class Exception extends \ErrorException {}
}

namespace Model {

    class Validator {
        
        private $_errors;

        function errors() {
            return $this->_errors;
        }

        function validate($key, $assertion, $message) {
            if (!$assertion) {
                $this->_errors[$key] = $message;
            }

            return $this;
        }

        function done() {
            if (count($this->_errors) > 0) {
                throw new \Model\Validator\Exception;
            }
        }
    }

}