<?php

namespace Gini;

require __DIR__ . '/bootstrap.php';

class Application
{
    public static function setup()
    {
        CLI::setup();
    }

    public static function main($argv)
    {
        // $argv包括了我们的cli标准脚本, 因此需要删除
        array_shift($argv);
        CLI::main($argv);
    }

    public static function shutdown()
    {
        CLI::shutdown();
    }

    public static function exception($e)
    {
        CLI::exception($e);
    }

}
