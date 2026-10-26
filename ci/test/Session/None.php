<?php

namespace Gini\PHPUnit\Session;

class None extends \Gini\PHPUnit\TestCase\Unit
{
    private function handler(): \Gini\Session\None
    {
        return new \Gini\Session\None();
    }

    public function testOpenReturnsTrue(): void
    {
        self::assertTrue($this->handler()->open('/tmp', 'sid'));
    }

    public function testCloseReturnsTrue(): void
    {
        self::assertTrue($this->handler()->close());
    }

    public function testReadReturnsEmptyString(): void
    {
        self::assertSame('', $this->handler()->read('whatever'));
    }

    public function testWriteReturnsTrue(): void
    {
        self::assertTrue($this->handler()->write('sid', 'data'));
    }

    public function testDestroyReturnsTrue(): void
    {
        self::assertTrue($this->handler()->destroy('sid'));
    }

    public function testGcReturnsFalseNoOp(): void
    {
        // gc 永远不回收: 契约为 false
        self::assertFalse($this->handler()->gc(3600));
    }

    public function testImplementsSessionHandlerInterface(): void
    {
        self::assertInstanceOf(\SessionHandlerInterface::class, $this->handler());
    }
}
