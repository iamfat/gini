<?php

namespace Gini\CGI\Middleware {

    class UtControllerStub implements \Gini\CGI\Middleware\Prototype
    {
        public static $calls = [];

        public function process($controller, $action, $params)
        {
            self::$calls[] = [get_class($controller), $action, $params];
        }
    }
}

namespace Gini\PHPUnit\Controller {

    use Gini\CGI\Middleware\UtControllerStub;
    use Gini\CGI\Response;

    class CGI extends \Gini\PHPUnit\TestCase\Unit
    {
        private $serverBackup;
        private $globalsBackup;

        protected function setUp(): void
        {
            parent::setUp();
            $this->serverBackup = $_SERVER;
            $this->globalsBackup = ['get' => $_GET, 'post' => $_POST, 'files' => $_FILES, 'cookie' => $_COOKIE];
            UtControllerStub::$calls = [];
        }

        protected function tearDown(): void
        {
            $_SERVER = $this->serverBackup;
            list($_GET, $_POST, $_FILES, $_COOKIE) = array_values($this->globalsBackup);
            // CGI::$route 为 static, 测试后归位 null
            $prop = new \ReflectionProperty('\Gini\CGI', 'route');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
            parent::tearDown();
        }

        public function testExecuteRunsPreActionActionPostActionChain(): void
        {
            $c = new UtCGIController();
            $c->action = 'actionShow';
            $c->params = ['7'];
            $c->env = ['get' => ['gk' => 'gv'], 'post' => ['pk' => 'pv']];
            $expected = new Response\Nothing();
            $c->actionReturns = $expected;

            $response = $c->execute();

            self::assertSame($expected, $response);
            self::assertCount(3, $c->log);
            // pre: actionName 已剥 'action' 前缀
            self::assertSame(['pre', 'Show', ['7']], $c->log[0]);
            // action: 位置参数经反射补默认值; form() 为 get+post 合并
            self::assertSame('actionShow', $c->log[1][0]);
            self::assertSame('7', $c->log[1][1]);
            self::assertSame('default', $c->log[1][2]);
            self::assertSame(['gk' => 'gv', 'pk' => 'pv'], $c->log[1][3]);
            self::assertSame('post', $c->log[2][0]);
        }

        public function testExecutePostActionResponseReplacesActionResponse(): void
        {
            $c = new UtCGIController();
            $c->action = 'actionShow';
            $c->actionReturns = new Response\Nothing();
            $replacement = new Response\Nothing();
            $c->postReturns = $replacement;

            self::assertSame($replacement, $c->execute());
        }

        public function testExecutePreActionFalseSkipsActionButRunsPostAction(): void
        {
            $c = new UtCGIController();
            $c->action = 'actionShow';
            $c->preReturns = false;

            $response = $c->execute();

            // action 未执行, preAction 返回 false, postAction 返回 null → 兜底 Nothing
            self::assertInstanceOf(Response\Nothing::class, $response);
            self::assertSame('pre', $c->log[0][0]);
            self::assertSame('post', $c->log[1][0]);
            self::assertCount(2, $c->log);
        }

        public function testExecuteNullActionResponseBecomesNothing(): void
        {
            $c = new UtCGIController();
            $c->action = 'actionShow';
            $c->actionReturns = null;

            self::assertInstanceOf(Response\Nothing::class, $c->execute());
        }

        public function testExecuteDerivesActionFromParamsAndShiftsSegment(): void
        {
            // 'foo-bar' → strtr 去分隔符 → 'foobar' → method_exists 大小写不敏感命中 actionFooBar
            $c = new UtCGIController();
            $c->params = ['foo-bar', '9'];
            $c->actionReturns = new Response\Nothing();

            $c->execute();

            // actionName 为小写 'foobar'(未做大小写归一), 方法名匹配靠 PHP 大小写不敏感
            self::assertSame(['pre', 'foobar', ['9']], $c->log[0]);
            self::assertSame('actionFooBar', $c->log[1][0]);
            // 首段被 shift, 剩余作为位置参数
            self::assertSame('9', $c->log[1][1]);
        }

        public function testExecuteUnknownSegmentFallsBackToIndex(): void
        {
            $c = new UtCGIIndexController();
            $c->params = ['nonexistent'];
            $c->actionReturns = new Response\Nothing();

            $c->execute();

            // actionName 为 null, 走 __index
            self::assertSame(['pre', null, ['nonexistent']], $c->log[0]);
            self::assertSame('__index', $c->log[1][0]);
        }

        public function testExecuteWithoutIndexYields404ErrorResponse(): void
        {
            $c = new UtCGIController();
            $c->params = ['nonexistent'];

            $response = $c->execute();

            // 无 __index 可回退 → Response\Exception 404 → errorResponse 渲染真实视图
            self::assertInstanceOf(Response\HTML::class, $response);
            // error/http 模板仅一行 h1; code/message 来自异常
            self::assertSame('<h1>404 Not Found</h1>', (string) $response);
            self::assertSame([], $c->log, 'pre/post 均不应执行');
        }

        public function testExecuteActionThrowingResponseExceptionProducesErrorResponse(): void
        {
            $c = new UtCGIController();
            $c->action = 'actionBoom';

            $response = $c->execute();

            self::assertInstanceOf(Response\HTML::class, $response);
            self::assertSame('<h1>401 Unauthorized</h1>', (string) $response);
        }

        public function testExecuteNonResponseExceptionPropagates(): void
        {
            $c = new UtCGIController();
            $c->action = 'actionGenericError';

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('boom');
            $c->execute();
        }

        public function testExecuteResolvesStringMiddlewareViaMiddlewareOf(): void
        {
            $c = new UtCGIController();
            $c->action = 'actionShow';
            $c->middlewares = ['ut-controller-stub'];
            $c->actionReturns = new Response\Nothing();

            $c->execute();

            // 'ut-controller-stub' → strtr 去分隔符 → \Gini\CGI\Middleware\UtControllerStub
            self::assertCount(1, UtControllerStub::$calls);
            list($cls, $action, $params) = UtControllerStub::$calls[0];
            self::assertSame(UtCGIController::class, $cls);
            self::assertSame('Show', $action);
            self::assertSame([], $params, '显式 action 时 params 不含派生段');
        }

        public function testExecuteAcceptsObjectMiddlewareDirectly(): void
        {
            $mw = new UtControllerStub();
            $c = new UtCGIController();
            $c->action = 'actionShow';
            $c->middlewares = [$mw];
            $c->actionReturns = new Response\Nothing();

            $c->execute();

            self::assertCount(1, UtControllerStub::$calls);
        }

        public function testFormReadsEnvByMode(): void
        {
            $c = new UtCGIController();
            $c->env = [
                'get' => ['gk' => 'gv', 'shared' => 'from-get'],
                'post' => ['pk' => 'pv', 'shared' => 'from-post'],
                'files' => ['f' => 'fv'],
            ];

            self::assertSame(['gk' => 'gv', 'shared' => 'from-get'], $c->form('get'));
            self::assertSame(['pk' => 'pv', 'shared' => 'from-post'], $c->form('post'));
            self::assertSame(['f' => 'fv'], $c->form('files'));
            // 默认模式 post 覆盖 get
            self::assertSame(
                ['gk' => 'gv', 'shared' => 'from-post', 'pk' => 'pv'],
                $c->form()
            );
        }

        public function testFunctionArgumentsPositionalFillsReflectionDefaults(): void
        {
            // functionArguments 仅接受字符串函数名或 [obj, method]; Closure 走 ReflectionMethod 分支会抛错
            $fn = __NAMESPACE__ . '\utFnTwoParams';

            self::assertSame(['7', 'dflt'], \Gini\CGI::functionArguments($fn, ['7']));
            // 参数多于形参: max() 扩展, 多余参数透传(供 func_get_args)
            self::assertSame([1, 2, 3], \Gini\CGI::functionArguments($fn, [1, 2, 3]));
            // ReflectionMethod 形态
            $c = new UtCGIController();
            self::assertSame(
                ['9', 'default'],
                \Gini\CGI::functionArguments([$c, 'actionShow'], ['9'])
            );
        }

        public function testFunctionArgumentsEmptyParamsYieldDefaults(): void
        {
            // 空位置参数落入字符串键分支: 每个形参取默认值/ null
            self::assertSame([null, 'x'], \Gini\CGI::functionArguments(__NAMESPACE__ . '\utFnUserId', []));
        }

        public function testFunctionArgumentsStringKeysNormalizeAndMergeForm(): void
        {
            $fn = __NAMESPACE__ . '\utFnUserId';

            // user_id / user-id / userId 三种形态归一后命中 $userId
            self::assertSame([3, 'x'], \Gini\CGI::functionArguments($fn, ['user_id' => 3]));
            self::assertSame([3, 'x'], \Gini\CGI::functionArguments($fn, ['user-id' => 3]));

            // form 先合并, params 同名覆盖; extra 仅在 form 中 → 取 form 值
            self::assertSame(
                [3, 'from-form'],
                \Gini\CGI::functionArguments($fn, ['user_id' => 3], ['user-id' => 5, 'extra' => 'from-form'])
            );
            // params 无匹配时 form 全量生效
            self::assertSame(
                [5, 'from-form'],
                \Gini\CGI::functionArguments($fn, ['other' => 9], ['user_id' => 5, 'extra' => 'from-form'])
            );

            // 无匹配且无默认 → null
            self::assertSame([null, 'x'], \Gini\CGI::functionArguments($fn, ['unrelated' => 1]));
        }

        public function testDefaultEnvHonorsMethodOverrideAndQueryReparsing(): void
        {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'DELETE';
            $_SERVER['QUERY_STRING'] = 'a=1&b=apple';
            $_GET = ['stale' => 'yes'];
            \Gini\CGI::route('/ut/route');

            $env = \Gini\CGI::defaultEnv();

            // 覆盖方法回写 REQUEST_METHOD
            self::assertSame('DELETE', $env['method']);
            self::assertSame('DELETE', $_SERVER['REQUEST_METHOD']);
            // QUERY_STRING 重解析替换 $_GET
            self::assertSame(['a' => '1', 'b' => 'apple'], $env['get']);
            self::assertSame(['a' => '1', 'b' => 'apple'], $_GET);
            self::assertSame('/ut/route', $env['route']);
            self::assertSame($_POST, $env['post']);
            self::assertSame($_FILES, $env['files']);
            self::assertSame($_SERVER, $env['server']);
            self::assertSame($_COOKIE, $env['cookie']);
        }

        public function testDefaultEnvDefaultsMethodToGet(): void
        {
            unset($_SERVER['REQUEST_METHOD'], $_SERVER['QUERY_STRING'], $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);

            $env = \Gini\CGI::defaultEnv();

            self::assertSame('GET', $env['method']);
            // 未设置时 route 键存在但值为 null
            self::assertNull($env['route']);
        }
    }

    class UtCGIController extends \Gini\Controller\CGI
    {
        public $log = [];
        public $actionReturns;
        public $preReturns;
        public $postReturns;

        protected function __preAction($action, &$params)
        {
            $this->log[] = ['pre', $action, $params];

            return $this->preReturns;
        }

        protected function __postAction($action, &$params, $response)
        {
            $this->log[] = ['post', $action, $params];

            return $this->postReturns;
        }

        public function actionShow($id = null, $name = 'default')
        {
            $this->log[] = ['actionShow', $id, $name, $this->form()];

            return $this->actionReturns;
        }

        public function actionFooBar($id = null)
        {
            $this->log[] = ['actionFooBar', $id];

            return $this->actionReturns;
        }

        public function actionBoom()
        {
            throw new Response\Exception(null, 401);
        }

        public function actionGenericError()
        {
            throw new \RuntimeException('boom');
        }
    }

    class UtCGIIndexController extends UtCGIController
    {
        public function __index($params)
        {
            $this->log[] = ['__index', $params];

            return $this->actionReturns;
        }
    }

    function utFnTwoParams($a, $b = 'dflt')
    {
        return func_get_args();
    }

    function utFnUserId($userId, $extra = 'x')
    {
        return func_get_args();
    }
}
