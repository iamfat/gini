<?php

namespace Gini {
    
    require __DIR__ . '/bootstrap.php';

    class Application { }

}

namespace Gini\PHPUnit {
    
    abstract class CGI extends \PHPUnit_Framework_TestCase {
        public static function setUpBeforeClass() {
            \Gini\CGI::setup();
        }
        
        public static function tearDownAfterClass() {
            \Gini\CGI::shutdown();
        }
    }

    abstract class CLI extends \PHPUnit_Framework_TestCase {
        public static function setUpBeforeClass() {
            \Gini\CLI::setup();
        }
        
        public static function tearDownAfterClass() {
            \Gini\CLI::shutdown();
        }
    }

}
