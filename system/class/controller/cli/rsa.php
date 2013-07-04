<?php

namespace Controller\CLI {

    class RSA extends \Controller\CLI {
    
        function action_pubout(&$args) {
            $key = $args[0];
            $rsa = new \Model\RSA($key);
            echo $rsa->public_key() . "\n";
        }

    }

}