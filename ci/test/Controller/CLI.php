<?php

namespace Gini\PHPUnit\Controller;

class CLI extends \Gini\PHPUnit\TestCase\Unit
{
    public function testExecuteDefaultsToIndexAndUnknownEchoes(): void
    {
        $c = new UtCLIController();
        // action 为空 → __index → 委托 __unknown 输出提示
        ob_start();
        $response = $c->execute();
        $output = ob_get_clean();

        self::assertNull($response, '__unknown 无返回值, postAction 未覆盖时透传 null');
        self::assertStringContainsString('unknown command', $output);
        // __preAction 以派生的 '__index' 调用
        self::assertSame(['pre', '__index', []], $c->log[0]);
        self::assertSame('post', $c->log[1][0]);
    }

    public function testExecuteDispatchesToActionMethodWithParamsArray(): void
    {
        $c = new UtCLIController();
        $c->action = 'hello';
        $c->params = ['a', 'b'];

        $response = $c->execute();

        self::assertSame('hello-resp', $response);
        // CLI 与 CGI 不同: params 以数组整体作为单一实参传入
        self::assertSame(['hello', ['a', 'b']], $c->log[1]);
    }

    public function testExecutePostActionCanReplaceResponse(): void
    {
        $c = new UtCLIController();
        $c->action = 'hello';
        $c->postReturns = 'replaced';

        self::assertSame('replaced', $c->execute());
    }

    public function testExecutePreActionReturnIsIgnored(): void
    {
        // CLI 的 preAction 返回值不参与跳过逻辑(与 CGI 的 false 跳过不同)
        $c = new UtCLIController();
        $c->action = 'hello';
        $c->preReturns = false;

        self::assertSame('hello-resp', $c->execute());
        self::assertSame('hello', $c->log[1][0]);
    }

    public function testExecuteParamsNullNormalizedToEmptyArray(): void
    {
        $c = new UtCLIController();
        $c->action = 'hello';
        $c->params = null;

        $c->execute();

        self::assertSame(['hello', []], $c->log[1]);
    }

    public function testExecuteUnknownActionMethodBehavior(): void
    {
        $c = new UtCLIController();
        $c->action = 'nonexistent';

        // 现状钉扎: PHP 8 下 call_user_func 非法回调抛 TypeError(\Error 子类);
        // PHP 7.4 为 E_WARNING + 返回 null(@ 抑制告警后可观察)
        if (\PHP_VERSION_ID >= 80000) {
            $this->expectException(\Error::class);
            $c->execute();
        } else {
            self::assertNull(@$c->execute());
        }
    }
}

class UtCLIController extends \Gini\Controller\CLI
{
    public $log = [];
    public $preReturns;
    public $postReturns;

    protected function __preAction($action, &$params)
    {
        $this->log[] = ['pre', $action, $params];

        return $this->preReturns;
    }

    protected function __postAction($action, &$params, $response)
    {
        $this->log[] = ['post', $action, $params, $response];

        return $this->postReturns;
    }

    public function hello($params)
    {
        $this->log[] = ['hello', $params];

        return 'hello-resp';
    }
}
