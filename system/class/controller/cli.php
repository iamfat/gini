<?php

namespace Controller;
    
abstract class CLI {

    function __index(&$args) {
        echo "\e[1;34mgini\e[0m: unknown command.\n";
    }

    function action_help(&$args) {
        echo "\e[1;34mgini\e[0m: help is unavailable.\n";
    }
        
}

