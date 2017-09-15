<?php

namespace Gini {

    require __DIR__ . '/bootstrap.php';

    class Application { }

}

namespace Gini\PHPUnit {

    use \PHPUnit\Framework\TestCase;
    
    abstract class CGI extends TestCase
    {
        public static function setUpBeforeClass()
        {
            \Gini\CGI::setup();
        }

        public static function tearDownAfterClass()
        {
            \Gini\CGI::shutdown();
        }
    }

    abstract class CLI extends TestCase
    {
        public static function setUpBeforeClass()
        {
            \Gini\CLI::setup();
        }

        public static function tearDownAfterClass()
        {
            \Gini\CLI::shutdown();
        }
    }

}
