<?php

namespace Gini\Controller\API {

    class UtApiIndex
    {
        public $app;
        public $env;

        public function actionPing($msg = 'pong')
        {
            return $msg;
        }

        public function actionEnv()
        {
            return $this->env['route'];
        }

        public function actionBoom()
        {
            throw new \Gini\API\Exception('boom happened', -32000);
        }
    }

    class UtApiThing
    {
        public $app;
        public $env;

        public function __invoke($a = null, $b = null)
        {
            return [$a, $b];
        }
    }
}

namespace Gini\Controller\API\Ut {

    class Grouped
    {
        public $app;
        public $env;

        public function actionRun()
        {
            return 'ran';
        }
    }
}

namespace Gini\PHPUnit\Core {

    class API extends \Gini\PHPUnit\TestCase\Unit
    {
        private function valid(array $overrides = [])
        {
            return $overrides + [
                'jsonrpc' => '2.0',
                'method' => 'utapiindex.ping',
                'params' => [],
                'id' => 7,
            ];
        }

        public function testMissingRequiredFieldsYieldsInvalidRequest(): void
        {
            // 缺 method
            $res = \Gini\API::dispatch(['jsonrpc' => '2.0', 'params' => [], 'id' => 1]);
            self::assertSame(-32600, $res['error']['code']);
            self::assertSame('Invalid Request', $res['error']['message']);
            self::assertSame(1, $res['id']);

            // 缺 params
            $res = \Gini\API::dispatch(['jsonrpc' => '2.0', 'method' => 'x', 'id' => 2]);
            self::assertSame(-32600, $res['error']['code']);

            // 空 method
            $res = \Gini\API::dispatch($this->valid(['method' => '']));
            self::assertSame(-32600, $res['error']['code']);
        }

        public function testWrongJsonRpcVersionYieldsInvalidRequest(): void
        {
            $res = \Gini\API::dispatch($this->valid(['jsonrpc' => '1.0']));

            self::assertSame(-32600, $res['error']['code']);
            self::assertSame('Invalid Request', $res['error']['message']);
        }

        public function testUnknownMethodYieldsMethodNotFound(): void
        {
            $res = \Gini\API::dispatch($this->valid(['method' => 'no.such.thing']));

            self::assertSame(-32601, $res['error']['code']);
            self::assertSame('Method not found', $res['error']['message']);
            self::assertSame(7, $res['id']);
        }

        public function testUnderscorePrefixedMethodIsRejected(): void
        {
            $res = \Gini\API::dispatch($this->valid(['method' => 'utapiindex._hidden']));

            self::assertSame(-32601, $res['error']['code']);
        }

        public function testDispatchInvokesActionWithDefaultAndNamedParams(): void
        {
            $res = \Gini\API::dispatch($this->valid());

            self::assertSame('2.0', $res['jsonrpc']);
            self::assertSame('pong', $res['result']);
            self::assertSame(7, $res['id']);

            // 字符串键 params 经 functionArguments 命名匹配
            $res = \Gini\API::dispatch($this->valid(['params' => ['msg' => 'echo!']]));
            self::assertSame('echo!', $res['result']);
        }

        public function testDispatchPassesEnvToController(): void
        {
            $res = \Gini\API::dispatch($this->valid(['method' => 'utapiindex.env']), ['route' => '/ut/route']);

            self::assertSame('/ut/route', $res['result']);
        }

        public function testDispatchPrefersInvokeMagicMethod(): void
        {
            $res = \Gini\API::dispatch($this->valid(['method' => 'utapithing', 'params' => [1, 2]]));

            self::assertSame([1, 2], $res['result']);
        }

        public function testDispatchResolvesNestedNamespace(): void
        {
            $res = \Gini\API::dispatch($this->valid(['method' => 'ut.grouped.run']));

            self::assertSame('ran', $res['result']);
        }

        public function testControllerExceptionBecomesErrorResponse(): void
        {
            $res = \Gini\API::dispatch($this->valid(['method' => 'utapiindex.boom', 'id' => 5]));

            self::assertSame(-32000, $res['error']['code']);
            self::assertSame('boom happened', $res['error']['message']);
            self::assertSame(5, $res['id']);
        }

        public function testNotificationWithoutIdReturnsNull(): void
        {
            // 现状钉扎: 成功且无 id 时 $response 未赋值 → 返回 null
            // (未定义变量告警由 Core::error 之外的默认链处理, 7.4 E_NOTICE / 8.5 E_WARNING)
            $res = \Gini\API::dispatch($this->valid(['id' => null]));

            self::assertNull($res);
        }
    }
}
