<?php

namespace Gini\PHPUnit\Database;

class SQL extends \Gini\PHPUnit\TestCase\Unit
{
    public function testStringSQLStringifiesAsIs(): void
    {
        $sql = new \Gini\Database\SQL('NOW()');
        self::assertSame('NOW()', (string) $sql);
        self::assertSame('123', (string) new \Gini\Database\SQL(123));
    }

    public function testArraySQLJoinedWithDefaultSeparator(): void
    {
        $sql = new \Gini\Database\SQL(['a = 1', 'b = 2']);
        self::assertSame('a = 1,b = 2', (string) $sql);
    }

    public function testArraySQLJoinedWithCustomSeparator(): void
    {
        $sql = new \Gini\Database\SQL(['a = 1', 'b = 2'], ' AND ');
        self::assertSame('a = 1 AND b = 2', (string) $sql);
    }

    public function testEmptyArrayRendersEmptyString(): void
    {
        self::assertSame('', (string) new \Gini\Database\SQL([]));
        self::assertSame('', (string) new \Gini\Database\SQL([], ' AND '));
    }
}
