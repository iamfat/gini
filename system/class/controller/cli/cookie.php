<?php

namespace Controller\CLI {
    
    class Cookie extends \Controller\CLI {

        static function action_cleanup($args) {
            \Gini\Cookie::cleanup();
        }
        
        static function action_clean($args) {
            if (count($args) == 0) {
                die("Usage: gini cookie clean [key]\n");
            }
            
            \Gini\Cookie::set($args[0], null);
        }
    }

}
