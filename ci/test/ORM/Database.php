<?php

namespace Gini\PHPUnit\ORM;

class Database extends \Gini\PHPUnit\TestCase\CLI
{
    public function testPrepareSQL()
    {
        $db = new \Gini\Database('unknown:');

        list($SQL, $params) = $db->prepareSQL('SELECT :field FROM :table WHERE :field IN (:values) AND :expression AND foo=:bar AND t=:now', [
            ':table' => 'table1',
            ':field' => 'field1',
            ':expression' => SQL('hello=1')
        ], [
            ':values' => [1, 2, 3],
            ':bar' => 'bar',
            ':now' => SQL('NOW()')
        ]);

        self::assertEquals('SELECT "field1" FROM "table1" WHERE "field1" IN (1,2,3) AND hello=1 AND foo=:bar AND t=NOW()', $SQL);
        self::assertEquals([':bar' => 'bar'], $params);
    }

    public function testPrepareSQLWithNullIdentsAndParams(): void
    {
        $db = new \Gini\Database('unknown:');
        list($SQL, $params) = $db->prepareSQL('SELECT 1', null, null);
        self::assertSame('SELECT 1', $SQL);
        self::assertSame([], $params);
    }

    public function testPrepareSQLFiltersScalarAndBoolParams(): void
    {
        $db = new \Gini\Database('unknown:');
        list($SQL, $params) = $db->prepareSQL(
            'SELECT * FROM t WHERE a=:a AND b=:b AND c=:c',
            null,
            [':a' => 'str', ':b' => 5, ':c' => true]
        );
        self::assertSame('SELECT * FROM t WHERE a=:a AND b=:b AND c=:c', $SQL);
        // 非标量替换的参数原样保留给 prepared statement
        self::assertSame([':a' => 'str', ':b' => 5, ':c' => true], $params);
    }

    public function testPrepareSQLInlinesSQLIdentAndParam(): void
    {
        $db = new \Gini\Database('unknown:');
        list($SQL, $params) = $db->prepareSQL(
            'SELECT :f FROM t WHERE x=:x',
            [':f' => SQL('COUNT(*)')],
            [':x' => SQL('NOW()')]
        );
        // quoteIdent 遇到 SQL 对象直接取字面值
        self::assertSame('SELECT COUNT(*) FROM t WHERE x=NOW()', $SQL);
        self::assertSame([], $params);
    }

    public function testQuoteTypeBranches(): void
    {
        $db = new \Gini\Database('unknown:');
        self::assertSame('NULL', $db->quote(null));
        self::assertSame(1, $db->quote(true));
        self::assertSame(0, $db->quote(false));
        self::assertSame(42, $db->quote(42));
        self::assertSame(3.14, $db->quote(3.14));
        // 纯标量数字数组不需要 driver quote
        self::assertSame('1,2,3', $db->quote([1, 2, 3]));
        // SQL 对象原样
        self::assertSame('NOW()', $db->quote(SQL('NOW()')));
    }

    public function testQuoteIdentArrayAndIdentConcat(): void
    {
        $db = new \Gini\Database('unknown:');
        self::assertSame('"a","b"', $db->quoteIdent(['a', 'b']));
        self::assertSame('"db"."table"."field"', $db->ident('db', 'table', 'field'));
        self::assertSame('NOW()', $db->quoteIdent(SQL('NOW()')));
    }
}
