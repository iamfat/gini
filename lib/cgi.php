<?php

namespace Gini;

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
