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

    /**
     * 事务状态失配（驱动已不在事务中）时不得让 PDOException 逃逸。
     * PHP 7.4 与 8.x 的 PDO 在无活动事务时 commit/rollBack 均抛异常，
     * 因此这里是状态校验而非版本兼容。
     */
    public function testCommitWithoutActiveDriverTransactionDoesNotThrow(): void
    {
        $driver = $this->getMockBuilder('\Gini\Database\Unknown')
            ->disableOriginalConstructor()
            ->setMethods(['inTransaction', 'commit', 'rollBack', 'beginTransaction'])
            ->getMock();
        $driver->method('inTransaction')->willReturn(false);
        $driver->expects(self::never())->method('commit');
        $driver->expects(self::never())->method('rollBack');

        $db = (new \ReflectionClass(\Gini\Database::class))->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(\Gini\Database::class, '_driver');
        $prop->setAccessible(true);
        $prop->setValue($db, $driver);
        $level = new \ReflectionProperty(\Gini\Database::class, '_transactionLevel');
        $level->setAccessible(true);
        $level->setValue($db, 1);

        $db->commit();
        self::assertSame(0, $level->getValue($db));

        $level->setValue($db, 1);
        $db->rollback();
        self::assertSame(0, $level->getValue($db));
    }

    public function testCommitWithActiveDriverTransactionCommits(): void
    {
        $driver = $this->getMockBuilder('\Gini\Database\Unknown')
            ->disableOriginalConstructor()
            ->setMethods(['inTransaction', 'commit', 'rollBack'])
            ->getMock();
        $driver->method('inTransaction')->willReturn(true);
        $driver->expects(self::once())->method('commit');

        $db = (new \ReflectionClass(\Gini\Database::class))->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(\Gini\Database::class, '_driver');
        $prop->setAccessible(true);
        $prop->setValue($db, $driver);
        $level = new \ReflectionProperty(\Gini\Database::class, '_transactionLevel');
        $level->setAccessible(true);
        $level->setValue($db, 1);

        $db->commit();
        self::assertSame(0, $level->getValue($db));
    }
}
