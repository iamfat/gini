<?php

namespace Gini\PHPUnit\Core;

class ErrorHandler extends \Gini\PHPUnit\TestCase\CLI
{
    public function testDeprecationNotEscalated()
    {
        // align with Core::start() development error_reporting setting
        $oldLevel = error_reporting(E_ALL & ~E_NOTICE);

        try {
            $handled = \Gini\Core::error(E_DEPRECATED, 'deprecated test', __FILE__, __LINE__);
            self::assertTrue($handled, 'E_DEPRECATED must be handled without throwing');

            $handled = \Gini\Core::error(E_USER_DEPRECATED, 'user deprecated test', __FILE__, __LINE__);
            self::assertTrue($handled, 'E_USER_DEPRECATED must be handled without throwing');
        } finally {
            error_reporting($oldLevel);
        }
    }

    public function testSuppressedErrorNotEscalated()
    {
        $deprecationCaught = null;
        try {
            @trigger_error('suppressed user deprecation', E_USER_DEPRECATED);
        } catch (\ErrorException $e) {
            $deprecationCaught = $e;
        }
        self::assertNull($deprecationCaught, '@-suppressed E_USER_DEPRECATED must not be escalated');

        $warningCaught = null;
        try {
            @trigger_error('suppressed user warning', E_USER_WARNING);
        } catch (\ErrorException $e) {
            $warningCaught = $e;
        }
        self::assertNull($warningCaught, '@-suppressed E_USER_WARNING must not be escalated');
    }

    public function testUserWarningEscalated()
    {
        $this->expectException(\ErrorException::class);
        trigger_error('unsuppressed user warning', E_USER_WARNING);
    }
}
