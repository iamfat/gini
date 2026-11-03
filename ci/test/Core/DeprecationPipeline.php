<?php

namespace Gini\PHPUnit\Core;

/**
 * 弃用通告管道契约（openspec/specs/error-handling）：
 *  - 弃用 MUST 被记录，MUST NOT 升级为异常；
 *  - @ 抑制的通告 MUST NOT 抛异常、MUST NOT 写日志；
 *  - 非弃用诊断错误仍升级为异常。
 *
 * 注意：set_error_handler 的注册掩码无法从用户态读取，因此"引擎弃用是否
 * 真正进入 handler"由 Core::start() 的掩码配置保证，本文件通过
 * error_reporting 门槛与 handler 直接调用来钉住可观测行为。
 */
class DeprecationPipeline extends \Gini\PHPUnit\TestCase\Unit
{
    private $_savedReporting;

    protected function setUp(): void
    {
        $this->_savedReporting = error_reporting();
    }

    protected function tearDown(): void
    {
        error_reporting($this->_savedReporting);
    }

    /**
     * dev 与 production 两种掩码下，handler 都必须接手弃用（返回 true = 已处理）。
     */
    public function testDeprecationHandledUnderBothEnvironments(): void
    {
        $masks = [
            'development' => E_ALL & ~E_NOTICE,
            'production' => E_ALL & ~(E_NOTICE | E_WARNING),
        ];

        foreach ($masks as $env => $mask) {
            error_reporting($mask);
            self::assertTrue(
                \Gini\Core::error(E_DEPRECATED, 'engine deprecation', __FILE__, __LINE__),
                "$env: 引擎弃用未被 handler 接手"
            );
            self::assertTrue(
                \Gini\Core::error(E_USER_DEPRECATED, 'user deprecation', __FILE__, __LINE__),
                "$env: 用户弃用未被 handler 接手"
            );
        }
    }

    /**
     * @ 抑制时 error_reporting() 掩码位被清除，handler 必须交回引擎（不记录、不抛）。
     */
    public function testSuppressedDeprecationReturnsFalse(): void
    {
        error_reporting(E_ALL & ~(E_NOTICE | E_WARNING));

        $ret = @utf8_encode('x');
        self::assertIsString($ret);

        // 模拟 @ 抑制后的掩码状态：弃用位被移除
        error_reporting(E_ALL & ~(E_NOTICE | E_WARNING | E_DEPRECATED));
        self::assertFalse(\Gini\Core::error(E_DEPRECATED, 'engine deprecation', __FILE__, __LINE__));
    }

    /**
     * 非弃用诊断错误必须继续升级，且携带原始信息。
     */
    public function testUserWarningStillEscalates(): void
    {
        error_reporting(E_ALL & ~(E_NOTICE | E_WARNING));

        $this->expectException(\ErrorException::class);
        try {
            \Gini\Core::error(E_USER_WARNING, 'boom', __FILE__, __LINE__);
        } catch (\ErrorException $e) {
            self::assertSame('boom', $e->getMessage());
            // ErrorException 的 severity 参数在 Core::error 中固定为 1，
            // 原始错误级别通过 code 承载。
            self::assertSame(E_USER_WARNING, $e->getCode());
            throw $e;
        }
    }

    /**
     * 守护 Core::start() 的掩码配置：两个掩码都必须保留弃用位。
     * 本用例不修改 error_reporting，断言的是 bootstrap 之后的真实状态。
     * （若有人把 E_DEPRECATED 从 Core::start 的 error_reporting 抹掉，此处失败）
     */
    public function testStartKeepsDeprecationLevelsInErrorReporting(): void
    {
        self::assertNotSame(0, error_reporting() & E_DEPRECATED,
            'Core::start() 的 error_reporting 掩码丢失了 E_DEPRECATED，弃用兜底失效');
        self::assertNotSame(0, error_reporting() & E_USER_DEPRECATED,
            'Core::start() 的 error_reporting 掩码丢失了 E_USER_DEPRECATED，弃用兜底失效');
    }

    /**
     * 老业务代码在 PHP 8 下最常见的弃用来源，经框架 handler 后必须
     * 与 7.4 结果一致地运行（openspec/specs/legacy-runtime-compat）。
     * 真实引擎触发（非手工构造），双版本结果一致即为兼容。
     */
    public function testLegacyNullArgCallsReturnSameValue(): void
    {
        error_reporting($this->_savedReporting);

        self::assertSame('x', @utf8_encode('x'));
        self::assertSame(0, strlen(@null));
        self::assertSame('', @str_replace('a', 'b', null));
        self::assertSame(false, @array_key_exists(null, []));
    }

    /**
     * TypeError / Error 无法被错误处理器吸收（openspec/specs/
     * legacy-runtime-compat 明确为业务必修），但框架的 Core::exception
     * 必须能以异常形式最终接管（register_shutdown/exception 兜底链）。
     */
    public function testHardFailuresAreCatchableThorwables(): void
    {
        $caught = null;
        try {
            $r = count(null);
            if (PHP_VERSION_ID < 80000) {
                // 7.4: warning + 返回 0，不抛
                $caught = 'PHP7:0';
            }
        } catch (\TypeError $e) {
            $caught = $e;
        }
        if (PHP_VERSION_ID < 80000) {
            self::assertSame('PHP7:0', $caught, "PHP 7.4 下 count(null) 应返回 0");
        } else {
            self::assertInstanceOf(\TypeError::class, $caught,
                'PHP 8+ 下 count(null) 是 TypeError，错误处理器无法吸收（业务必修）');
        }
    }
}
