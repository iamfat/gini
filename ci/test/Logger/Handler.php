<?php

namespace Gini\PHPUnit\Logger;

class Handler extends \Gini\PHPUnit\TestCase\Unit
{
    private $defaultBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultBackup = \Gini\Config::get('logger.default');
    }

    protected function tearDown(): void
    {
        foreach (['ut-multi', 'ut-disabled', 'ut-fallback'] as $key) {
            \Gini\Config::set('logger.' . $key, null);
        }
        \Gini\Config::set('logger.default', $this->defaultBackup);
        parent::tearDown();
    }

    public function testOfReturnsSingletonPerName(): void
    {
        self::assertSame(
            \Gini\Logger::of('ut-handler-a'),
            \Gini\Logger::of('ut-handler-a')
        );
    }

    public function testLogDispatchesToAllConfiguredHandlers(): void
    {
        // Console + ErrorLog 双 handler: 一次 log 调用两路分发
        \Gini\Config::set('logger.ut-multi', [
            'Console' => ['level' => 'debug'],
            'ErrorLog' => ['level' => 'debug'],
        ]);
        $logger = new \Gini\Logger('ut-multi');

        $logfile = tempnam(sys_get_temp_dir(), 'ut-logger');
        $orig = ini_set('error_log', $logfile);
        try {
            ob_start();
            $logger->warning('multi {k}', ['k' => 'v']);
            $out = ob_get_clean();
        } finally {
            ini_set('error_log', $orig);
        }

        self::assertSame("[ut-multi] multi v\n", $out, 'Console handler 输出');
        self::assertStringContainsString(
            '[WARNING] [ut-multi] multi v',
            file_get_contents($logfile),
            'ErrorLog handler 输出'
        );
        unlink($logfile);
    }

    public function testNonArrayHandlerOptionsAreSkipped(): void
    {
        // 'disabled'(字符串) 配置项被忽略, 仅 ErrorLog 生效
        \Gini\Config::set('logger.ut-disabled', [
            'Console' => 'disabled',
            'ErrorLog' => ['level' => 'error'],
        ]);
        $logger = new \Gini\Logger('ut-disabled');

        $logfile = tempnam(sys_get_temp_dir(), 'ut-logger');
        $orig = ini_set('error_log', $logfile);
        try {
            ob_start();
            $logger->error('hidden console');
            self::assertSame('', ob_get_clean(), 'Console 未装载不应有输出');
        } finally {
            ini_set('error_log', $orig);
        }

        self::assertStringContainsString('[ERROR] [ut-disabled] hidden console', file_get_contents($logfile));
        unlink($logfile);

        $prop = new \ReflectionProperty(\Gini\Logger::class, '_handlers');
        $prop->setAccessible(true);
        $handlers = $prop->getValue($logger);

        self::assertCount(1, $handlers);
        self::assertInstanceOf(\Gini\Logger\ErrorLog::class, $handlers[0]);
    }

    public function testUnconfiguredLoggerFallsBackToDefaultConfig(): void
    {
        \Gini\Config::set('logger.default', [
            'Console' => ['level' => 'info'],
        ]);
        \Gini\Config::set('logger.ut-fallback', null);

        $logger = new \Gini\Logger('ut-fallback');

        ob_start();
        $logger->notice('from default');
        $out = ob_get_clean();

        // ident 用的是 logger 实名而非 default
        self::assertSame("[ut-fallback] from default\n", $out);
    }

    public function testErrorLogHandlerFlattensNestedContextKeys(): void
    {
        // 直构 handler; level null → 默认 DEBUG
        $handler = new \Gini\Logger\ErrorLog('ut-errlog', null);

        $logfile = tempnam(sys_get_temp_dir(), 'ut-logger');
        $orig = ini_set('error_log', $logfile);
        try {
            $handler->log('error', 'user {user.id} bought {n}', ['user' => ['id' => 5], 'n' => 2]);
        } finally {
            ini_set('error_log', $orig);
        }

        self::assertStringContainsString(
            '[ERROR] [ut-errlog] user 5 bought 2',
            file_get_contents($logfile)
        );
        unlink($logfile);
    }

    public function testErrorLogHandlerSkipsBelowThreshold(): void
    {
        $handler = new \Gini\Logger\ErrorLog('ut-errlog', 'error');

        $logfile = tempnam(sys_get_temp_dir(), 'ut-logger');
        $orig = ini_set('error_log', $logfile);
        try {
            $handler->log('debug', 'not written');
        } finally {
            ini_set('error_log', $orig);
        }

        self::assertSame('', file_get_contents($logfile));
        unlink($logfile);
    }

    public function testBaseHandlerLogIsNoOp(): void
    {
        $handler = new HandlerNoOpFixture('ut', 'debug');

        ob_start();
        $handler->log('error', 'msg', ['a' => 1]);
        self::assertSame('', ob_get_clean());
    }
}

class HandlerNoOpFixture extends \Gini\Logger\Handler
{
}
