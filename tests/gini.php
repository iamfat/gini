<?php

namespace {
    
    require __DIR__.'/../bin/gini-phpunit.php';

    class Application {

        static function setup() {
            \Model\Cache::setup();
            \Model\Config::setup();
            \Model\Event::setup();
            \Model\I18N::setup();
            \Model\Logger::setup();
            \Model\Session::setup();
        }

        static function main($argv) {
            // DO NOTHING AND WAIT FOR PHPUNIT TEST CASE
        }

        static function shutdown() {
            \Model\Session::shutdown();		
        }

        static function exception($e) {
            
        }

    }

}

namespace Gini\PHPUnit {
    
    abstract class CGI extends \PHPUnit_Framework_TestCase {
        public static function setUpBeforeClass() {
            \Gini\Core::setup();
            \Model\CGI::setup();
        }
        
        public static function tearDownAfterClass() {
            \Model\CGI::shutdown();
        }
    }

    abstract class CLI extends \PHPUnit_Framework_TestCase {
        public static function setUpBeforeClass() {
            \Gini\Core::setup();
            \Model\CLI::setup();
        }
        
        public static function tearDownAfterClass() {
            \Model\CLI::shutdown();
        }
    }

}
