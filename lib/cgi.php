<?php

namespace Gini;

require_once __DIR__."/bootstrap.php";

class Application {

    static function setup() {
        CGI::setup();
    }

    static function main($args) {
        CGI::main($args);                   // 分派控制器
    }

    static function shutdown() {
        CGI::shutdown();
    }

    static function exception($e) {
        CGI::exception($e);
    }

}
