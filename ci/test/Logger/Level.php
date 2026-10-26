<?php

namespace Gini\PHPUnit\Logger;

class Level extends \Gini\PHPUnit\TestCase\Unit
{
    public function testLevelConstants(): void
    {
        self::assertSame('emergency', \Gini\Logger\Level::EMERGENCY);
        self::assertSame('alert', \Gini\Logger\Level::ALERT);
        self::assertSame('critical', \Gini\Logger\Level::CRITICAL);
        self::assertSame('error', \Gini\Logger\Level::ERROR);
        self::assertSame('warning', \Gini\Logger\Level::WARNING);
        self::assertSame('notice', \Gini\Logger\Level::NOTICE);
        self::assertSame('info', \Gini\Logger\Level::INFO);
        self::assertSame('debug', \Gini\Logger\Level::DEBUG);
    }

    public function testIsLoggableThreshold(): void
    {
        $handler = new LevelTestHandler('ut', \Gini\Logger\Level::WARNING);
        self::assertTrue($handler->isLoggable(\Gini\Logger\Level::ERROR));
        self::assertTrue($handler->isLoggable(\Gini\Logger\Level::WARNING));
        self::assertFalse($handler->isLoggable(\Gini\Logger\Level::NOTICE));
        self::assertFalse($handler->isLoggable(\Gini\Logger\Level::DEBUG));
    }

    public function testLevelValueMonotonicOrdering(): void
    {
        $handler = new LevelTestHandler('ut', \Gini\Logger\Level::DEBUG);
        $levels = [
            \Gini\Logger\Level::DEBUG,
            \Gini\Logger\Level::INFO,
            \Gini\Logger\Level::NOTICE,
            \Gini\Logger\Level::WARNING,
            \Gini\Logger\Level::ERROR,
            \Gini\Logger\Level::CRITICAL,
            \Gini\Logger\Level::ALERT,
            \Gini\Logger\Level::EMERGENCY,
        ];
        $values = array_map([$handler, 'levelValue'], $levels);
        $sorted = $values;
        sort($sorted);
        self::assertSame($sorted, $values);
    }

    public function testLoggerInterpolatesPlaceholdersViaConsoleHandler(): void
    {
        \Gini\Config::set('logger.ut-interp', [
            'Console' => ['level' => 'debug'],
        ]);
        $logger = new \Gini\Logger('ut-interp');
        ob_start();
        $logger->info('hello {name}, n={n}', ['name' => 'world', 'n' => 3]);
        $output = ob_get_clean();
        self::assertSame("[ut-interp] hello world, n=3\n", $output);
    }

    public function testLoggerSkipsMessagesBelowThreshold(): void
    {
        \Gini\Config::set('logger.ut-thresh', [
            'Console' => ['level' => 'error'],
        ]);
        $logger = new \Gini\Logger('ut-thresh');
        ob_start();
        $logger->debug('not shown');
        $output = ob_get_clean();
        self::assertSame('', $output);
    }
}

class LevelTestHandler extends \Gini\Logger\Handler
{
    public function isLoggablePublic($level)
    {
        return $this->isLoggable($level);
    }
}
