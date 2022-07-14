<?php

namespace Gini\PHPUnit\TestCase\CGI;

use \Gini\CGI\Middleware\QuerySet;

class Middlewares extends \Gini\PHPUnit\TestCase\CLI
{
    public function testQuerySet()
    {
        // it should parse different types
        self::assertSame(QuerySet::parseSet('1'), 1, 'int');
        self::assertSame(QuerySet::parseSet('1.0'), 1.0, 'float');
        self::assertSame(QuerySet::parseSet('12m'), '12m', 'alphabet');
        self::assertSame(QuerySet::parseSet('true'), true, 'boolean');
        self::assertSame(QuerySet::parseSet('false'), false, 'boolean');
        self::assertSame(QuerySet::parseSet('null'), null, 'null');
        self::assertSame(QuerySet::parseSet(''), null, 'empty');

        // it should parse range
        self::assertSame(QuerySet::parseSet('(-15m,15m]'), [['gt', '-15m'], ['lte', '15m']]);
        self::assertSame(QuerySet::parseSet('(-15m,1000]'), [['gt', '-15m'], ['lte', 1000]]);
        self::assertSame(QuerySet::parseSet('[-1000,)'), [['gte', -1000]]);
        self::assertSame(QuerySet::parseSet('[1000,)'), [['gte', 1000]]);
        self::assertSame(QuerySet::parseSet('[,15m)'), [['lt', '15m']]);

        // it should parse set
        self::assertSame(QuerySet::parseSet('1,2,3m'), [1, 2, '3m']);
        self::assertSame(QuerySet::parseSet('{1,2,3m}'), [['or', [1, 2, '3m']]]);
        self::assertSame(QuerySet::parseSet('|1,2,3m'), [['or', [1, 2, '3m']]]);

        // it should parse not
        self::assertSame(QuerySet::parseSet('!true'), [['not', true]]);
        self::assertSame(QuerySet::parseSet('!1,2,3'), [['not', [1, 2, 3]]]);

        // it should parse string pattern
        // *abc*
        self::assertSame(QuerySet::parseSet('*'), [['like', '%']]);
        self::assertSame(QuerySet::parseSet('*abc*def*'), [['like', '%abc%def%']]);
        self::assertSame(QuerySet::parseSet('!1*2*3'), [['not like', '1%2%3']]);
        self::assertSame(QuerySet::parseSet('!1*,2*'), [['not', ['1*', '2*']]]);
        self::assertSame(QuerySet::parseSet('|a*,b*'), [['or', ['a*', 'b*']]]);
    }
}
