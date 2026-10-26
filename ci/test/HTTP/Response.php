<?php

namespace Gini\PHPUnit\HTTP;

class Response extends \Gini\PHPUnit\TestCase\CLI
{
    public function testConstructWithNullData()
    {
        // curl failure path: new HTTP\Response(null, 500)
        $r = new \Gini\HTTP\Response(null, 500);
        self::assertSame('', (string) $r);
        self::assertSame([], $r->header);

        // normal response keeps the original parsing behavior
        $ok = new \Gini\HTTP\Response("HTTP/1.0 200 OK\nContent-Type: text/plain\n\nbody");
        self::assertSame('body', (string) $ok);
        self::assertSame(['Content-Type' => 'text/plain'], $ok->header);
        self::assertSame('HTTP/1.0 200 OK', $ok->status);
        self::assertSame('200', $ok->status()->code);
    }

    public function testParsesStatusLineAcrossVersionsAndCodes(): void
    {
        $r = new \Gini\HTTP\Response("HTTP/2 404 Not Found\n\n");
        $status = $r->status();
        self::assertSame('2', $status->version);
        self::assertSame('404', $status->code);
        self::assertSame('Not Found', $status->text);

        $r500 = new \Gini\HTTP\Response("HTTP/1.0 500 Internal Server Error\n\n");
        $status = $r500->status();
        self::assertSame('1.0', $status->version);
        self::assertSame('500', $status->code);
        self::assertSame('Internal Server Error', $status->text);
    }

    public function testParsesMultipleHeadersAndCRLF(): void
    {
        $raw = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-Test: a: b\r\n\r\n  body line  \r\n";
        $r = new \Gini\HTTP\Response($raw);
        self::assertSame([
            'Content-Type' => 'application/json',
            'X-Test' => 'a: b',
        ], $r->header);
        // \r 被移除, body 两端 trim
        self::assertSame('body line', (string) $r);
    }

    public function testEmptyBodyAndHeaderDefaults(): void
    {
        $r = new \Gini\HTTP\Response("HTTP/1.1 204 No Content\n\n");
        self::assertSame('', (string) $r);
        self::assertSame([], $r->header);
        self::assertSame('204', $r->status()->code);
    }
}
