<?php

namespace Gini\PHPUnit\Core;

class URI extends \Gini\PHPUnit\TestCase\CLI
{
    private $_SERVER;
    private $rurl_mod;

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
        self::assertEquals(URL('hello?abc=1#def'), 'ftp://fake.com/hello?abc=1#def');

        self::assertEquals(URL('http://foo.bar/abc#def'), 'http://foo.bar/abc#def');
        self::assertEquals(URL('http://foo.bar/abc#def', ['foo' => 'bar']), 'http://foo.bar/abc?foo=bar#def');
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
        self::assertEquals(\Gini\URI::parseQuery("a[]=1&a[]=2&a[]=3"), ['a' => [1, 2, 3]]);
        self::assertEquals(\Gini\URI::parseQuery("a[0]=1&a[1]=2&a[2]=3"), ['a' => [1, 2, 3]]);
        self::assertEquals(\Gini\URI::parseQuery("a[a]=1&a[b]=2&a[c]=3"), ['a' => ['a' => 1, 'b' => 2, 'c' => 3]]);
        self::assertEquals(\Gini\URI::parseQuery("a[a][]=1&a[a][]=2"), ['a' => ['a' => [1, 2]]]);
        self::assertEquals(\Gini\URI::parseQuery("a[][]=1&a[][]=2"), ['a' => [[1, 2]]]);
        self::assertEquals(\Gini\URI::parseQuery("a[][a]=1&a[][b]=2"), ['a' => [['a' => 1, 'b' => 2]]]);
        self::assertEquals(\Gini\URI::buildQuery(['a' => [1, 2, 3], 'b' => 4]), "a=1&a=2&a=3&b=4");
    }

    public function testURLEdgeCases(): void
    {
        // mailto 分支: parse_url 对标准 mailto 不返回 user/host 键,
        // 框架实际拼出 "mailto:@"（记录既有行为）
        self::assertEquals('mailto:@', URL('mailto:user@example.com'));

        // 绝对 URL: 旧 query 与新 query 合并（数组）
        self::assertEquals(
            URL('http://foo.bar/path?a=1', ['b' => 2]),
            'http://foo.bar/path?a=1&b=2'
        );
        // 字符串形式 query
        self::assertEquals(
            URL('http://foo.bar/path', 'b=2'),
            'http://foo.bar/path?b=2'
        );
        // fragment 追加分隔符
        self::assertEquals(
            URL('http://foo.bar/a', null, 'frag'),
            'http://foo.bar/a#frag'
        );

        // 无 host 的相对路径走 base(); 无 forwarded/HTTPS 时 scheme 为 http
        unset($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTPS']);
        $_SERVER['HTTP_HOST'] = 'fake.com';
        $_SERVER['SCRIPT_NAME'] = '/x/index.php';
        \Gini\URI::setup();
        self::assertEquals(URL('hello', ['q' => 1]), 'http://fake.com/x/hello?q=1');
    }

    public function testRURLWithModQuery(): void
    {
        $_SERVER['HTTP_HOST'] = 'fake.com';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        \Gini\Config::set('system.rurl_mod', [
            'js' => ['query' => 'v=$(VERSION)'],
        ]);
        \Gini\URI::setup();

        self::assertEquals(
            RURL('hello.js', 'js'),
            'http://fake.com/assets/js/hello.js?v=' . APP_HASH
        );

        // 外部 URL 不拼 base, 但仍可追加 mod query
        self::assertStringStartsWith('http://abc.com/ade/hello.js?v=', RURL('http://abc.com/ade/hello.js', 'js'));
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
