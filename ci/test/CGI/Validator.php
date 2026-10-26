<?php

namespace Gini\PHPUnit\CGI;

class Validator extends \Gini\PHPUnit\TestCase\Unit
{
    public function testValidatePassesWithTruthyClosure(): void
    {
        $v = new \Gini\CGI\Validator();
        $returned = $v->validate('name', function () {
            return true;
        }, 'name is required');
        self::assertSame($v, $returned);
        self::assertSame([], $v->errors());
    }

    public function testValidateRecordsMessageOnFailure(): void
    {
        $v = new \Gini\CGI\Validator();
        $v->validate('name', false, 'name is required');
        self::assertSame(['name' => 'name is required'], $v->errors());
    }

    public function testValidateSkipsAssertionOnceKeyErrored(): void
    {
        $v = new \Gini\CGI\Validator();
        $calls = 0;
        $v->validate('name', false, 'first error');
        $v->validate('name', function () use (&$calls) {
            ++$calls;

            return true;
        }, 'should be ignored');
        self::assertSame(0, $calls);
        // 首次错误信息不被覆盖
        self::assertSame(['name' => 'first error'], $v->errors());
    }

    public function testDoneThrowsWhenErrorsPresent(): void
    {
        $v = new \Gini\CGI\Validator();
        $v->validate('name', false, 'required');
        $this->expectException(\Gini\CGI\Validator\Exception::class);
        $v->done();
    }

    public function testDoneSilentWhenValid(): void
    {
        $v = new \Gini\CGI\Validator();
        $v->validate('name', true, 'required');
        $v->done();
        self::assertSame([], $v->errors());
        self::assertTrue(true);
    }
}
