<?php

namespace Gini;

define('GINI_MUST_CACHE_AUTOLOAD', 1);

require_once __DIR__."/bootstrap.php";

class Application
{
    public static function setup()
    {
        CGI::setup();
    }

    public static function main($args)
    {
        CGI::main($args);                   // 分派控制器
    }

    public static function shutdown()
    {
        CGI::shutdown();
    }

    public static function exception($e)
    {
        CGI::exception($e);
    }

}
