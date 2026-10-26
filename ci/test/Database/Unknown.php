<?php

namespace Gini\PHPUnit\Database;

class Unknown extends \Gini\PHPUnit\TestCase\Unit
{
    private function driver(): \Gini\Database\Unknown
    {
        return new \Gini\Database\Unknown();
    }

    public function testQuoteIdentWrapsWithDoubleQuotes(): void
    {
        self::assertSame('"field"', $this->driver()->quoteIdent('field'));
    }

    public function testQuoteIdentEscapesEmbeddedQuotes(): void
    {
        // addslashes: 双引号与反斜杠被转义
        self::assertSame('"a\\"b"', $this->driver()->quoteIdent('a"b'));
        self::assertSame('"a\\\\b"', $this->driver()->quoteIdent('a\\b'));
    }

    public function testNoOpSchemaAndDdlContracts(): void
    {
        $d = $this->driver();
        self::assertFalse($d->tableExists('anything'));
        self::assertFalse($d->tableStatus('anything'));
        self::assertSame(['fields' => [], 'indexes' => []], $d->tableSchema('anything'));
        self::assertFalse($d->createTable('anything'));
        self::assertFalse($d->dropTable('anything'));
        self::assertFalse($d->emptyDatabase());
        // adjustTable/diagnose 为无返回值 no-op
        self::assertNull($d->adjustTable('anything', []));
        self::assertNull($d->diagnose());
    }
}
