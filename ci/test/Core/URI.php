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

        self::assertEquals(URL('hello'), 'ftp://fake.com/abc/hello');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        \Gini\URI::setup();
        self::assertEquals(URL('hello'), 'ftp://fake.com/hello');

        self::assertEquals(URL('http://foo.bar/abc'), 'http://foo.bar/abc');
    }

    public function testRURL()
    {
        $_SERVER['HTTP_HOST'] = 'fake.com';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'ftp';
        $_SERVER['SCRIPT_NAME'] = '/abc/index.php';
        $_SERVER['HTTPS'] = true;
        \Gini\Config::set('system.rurl_mod', []);
        \Gini\URI::setup();

        self::assertEquals(RURL('hello.js', 'js'), 'ftp://fake.com/abc/assets/js/hello.js');

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        \Gini\URI::setup();
        self::assertEquals(RURL('hello.js', 'js'), 'ftp://fake.com/assets/js/hello.js');

        self::assertEquals(RURL('http://abc.com/ade/hello.js', 'js'), 'http://abc.com/ade/hello.js');
    }

    public function testParseQuery()
    {
        self::assertEquals(\Gini\URI::parseQuery(""), []);
        self::assertEquals(\Gini\URI::parseQuery("?a=1&a=2&a=3&b=4"), ['a' => [1, 2, 3], 'b' => 4]);
        self::assertEquals(\Gini\URI::parseQuery("a=1&a=2&a=3&b=4"), ['a' => [1, 2, 3], 'b' => 4]);
        self::assertEquals(\Gini\URI::parseQuery("code=dSpC8jKtk9DQ3bhFMuN96FZYRCZDUnChqnym3MpC&redirect_uri=http%3A%2F%2F10.199.51.15%2Flims%2Fgapper_login%2Fauth%3Fsource%3Dgateway&grant_type=authorization_code&client_id=lims_seu&client_secret=c088c68c66bf6b83dfrwe4768d737438"), [
            'code' => 'dSpC8jKtk9DQ3bhFMuN96FZYRCZDUnChqnym3MpC', 
            'redirect_uri' => 'http://10.199.51.15/lims/gapper_login/auth?source=gateway',
            'grant_type' => 'authorization_code',
            'client_id' => 'lims_seu', 'client_secret' => 'c088c68c66bf6b83dfrwe4768d737438'
        ]);
        self::assertEquals(\Gini\URI::buildQuery(['a' => [1, 2, 3], 'b' => 4]), "a=1&a=2&a=3&b=4");
    }

    public function tearDown(): void
    {
        $_SERVER['HTTP_HOST'] = $this->_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = $this->_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTPS'] = $this->_SERVER['HTTPS'] ?? null;
        $_SERVER['SCRIPT_NAME'] = $this->_SERVER['SCRIPT_NAME'] ?? null;
        \Gini\Config::set('system.rurl_mod', $this->rurl_mod);
    }
}
