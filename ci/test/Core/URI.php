<?php

namespace Gini\PHPUnit\Core;

class URI extends \Gini\PHPUnit\TestCase\CLI
{
    private $_SERVER;

    public function setUp(): void
    {
        $this->_SERVER = $_SERVER;
        $this->rurl_mod = \Gini\Config::get('system.rurl_mod');
    }

    public function testURL()
    {
        $_SERVER['HTTP_HOST'] = 'fake.com';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'ftp';
        $_SERVER['SCRIPT_NAME'] = '/abc/index.php';
        $_SERVER['HTTPS'] = true;
        \Gini\URI::setup();

        $this->assertEquals(URL('hello'), 'ftp://fake.com/abc/hello');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        \Gini\URI::setup();
        $this->assertEquals(URL('hello'), 'ftp://fake.com/hello');

        $this->assertEquals(URL('http://foo.bar/abc'), 'http://foo.bar/abc');
    }

    public function testRURL()
    {
        $_SERVER['HTTP_HOST'] = 'fake.com';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'ftp';
        $_SERVER['SCRIPT_NAME'] = '/abc/index.php';
        $_SERVER['HTTPS'] = true;
        \Gini\Config::set('system.rurl_mod', []);
        \Gini\URI::setup();

        $this->assertEquals(RURL('hello.js', 'js'), 'ftp://fake.com/abc/assets/js/hello.js');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        \Gini\URI::setup();
        $this->assertEquals(RURL('hello.js', 'js'), 'ftp://fake.com/assets/js/hello.js');

        $this->assertEquals(RURL('http://abc.com/ade/hello.js', 'js'), 'http://abc.com/ade/hello.js');
    }

    public function tearDown(): void
    {
        $_SERVER['HTTP_HOST'] = $this->_SERVER['HTTP_HOST'];
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = $this->_SERVER['HTTP_HOST'];
        $_SERVER['HTTPS'] = $this->_SERVER['HTTPS'];
        $_SERVER['SCRIPT_NAME'] = $this->_SERVER['SCRIPT_NAME'];
        \Gini\Config::set('system.rurl_mod', $this->rurl_mod);
    }
}
