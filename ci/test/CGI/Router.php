<?php

namespace Gini\PHPUnit\CGI;

class Router extends \Gini\PHPUnit\TestCase\CLI
{
    private $boundKeys = [];

    public function tearDown(): void
    {
        foreach ($this->boundKeys as $key) {
            \Gini\IoC::clear($key);
        }
        $this->boundKeys = [];
        parent::tearDown();
    }

    private function bindFixture(): void
    {
        $key = '\\' . RouterFixtureController::class;
        \Gini\IoC::bind($key, function () {
            return new RouterFixtureController();
        });
        $this->boundKeys[] = $key;
    }

    public function testMagicCallRegistersHttpVerbsAndAny(): void
    {
        $router = new \Gini\CGI\Router();
        $ret = $router->POST('/x', '\\' . RouterFixtureController::class . '@show');
        self::assertSame($router, $ret);

        $rules = $router->rules();
        self::assertArrayHasKey('post:x', $rules);
        self::assertSame('post', $rules['post:x']['method']);

        $router->any('/y', '\\' . RouterFixtureController::class . '@show');
        self::assertArrayHasKey('*:y', $router->rules());

        // 未知魔术方法静默返回 null
        self::assertNull($router->frobnicate('/z'));
    }

    public function testParseRouteExtractsParamsAndRegex(): void
    {
        $router = new \Gini\CGI\Router('/api');
        $router->get('/users/{id}', '\\' . RouterFixtureController::class . '@show');
        $rule = $router->rules()['get:api/users/([^\\/]+)'];
        self::assertSame('api/users/{id}', $rule['route']);
        self::assertSame(['id'], $rule['params']);

        $router = new \Gini\CGI\Router();
        $router->get('/users/{id?}', '\\' . RouterFixtureController::class . '@show');
        self::assertSame(['id?'], $router->rules()['get:users/([^\\/]+)']['params']);
    }

    public function testDoubleColonDestNormalizesToAt(): void
    {
        $this->bindFixture();
        $router = new \Gini\CGI\Router();
        $router->get('/x', '\\' . RouterFixtureController::class . '::show');

        $controller = $router->dispatch('get', '/x');
        self::assertInstanceOf(RouterFixtureController::class, $controller);
        self::assertSame('show', $controller->action);
    }

    public function testClassPrefixOptionAppliesToUnqualifiedDest(): void
    {
        $this->bindFixture();
        $router = new \Gini\CGI\Router('', ['classPrefix' => '\\Gini\\PHPUnit\\CGI\\']);
        $router->get('/x', 'RouterFixtureController@show');

        $controller = $router->dispatch('GET', '/x');
        self::assertInstanceOf(RouterFixtureController::class, $controller);
    }

    public function testDispatchMatchesRouteParams(): void
    {
        $this->bindFixture();
        $router = new \Gini\CGI\Router();
        $router->get('/hello/{name}', '\\' . RouterFixtureController::class . '@show');

        $controller = $router->dispatch('get', '/hello/bob');
        self::assertSame('show', $controller->action);
        self::assertSame(['name' => 'bob'], $controller->params);
    }

    public function testDispatchWithoutActionUsesMethodDefault(): void
    {
        $this->bindFixture();
        $router = new \Gini\CGI\Router();
        $router->get('/ping', '\\' . RouterFixtureController::class);

        $controller = $router->dispatch('get', '/ping');
        self::assertSame('getDefault', $controller->action);
    }

    public function testDispatchNestedGroupResolvesInnerRule(): void
    {
        $this->bindFixture();
        $router = new \Gini\CGI\Router();
        $router->get('/api', function ($group) {
            $group->get('/users', '\\' . RouterFixtureController::class . '@list');
        });

        $controller = $router->dispatch('get', '/api/users');
        self::assertInstanceOf(RouterFixtureController::class, $controller);
        self::assertSame('list', $controller->action);
    }

    public function testDispatchMergesGlobalAndRouteMiddlewares(): void
    {
        $this->bindFixture();
        $router = new \Gini\CGI\Router();
        $router->via('mw.global');
        $router->via('/hello/{name}', 'mw.hello');
        $router->get('/hello/{name}', '\\' . RouterFixtureController::class . '@show');

        $controller = $router->dispatch('get', '/hello/bob');
        self::assertContains('mw.global', $controller->middlewares);
        self::assertContains('mw.hello', $controller->middlewares);

        // 控制器自带中间件保留; 合并后去重
        self::assertContains('mw.local', $controller->middlewares);
        self::assertSame(
            count($controller->middlewares),
            count(array_unique($controller->middlewares))
        );
        self::assertNotNull($controller->app);
    }

    public function testDispatchFallsBackToCgiIndexForUnknownRoute(): void
    {
        $key = '\\Gini\\Controller\\CGI\\Index';
        \Gini\IoC::bind($key, function () {
            return new RouterFixtureController();
        });
        $this->boundKeys[] = $key;

        $router = new \Gini\CGI\Router();
        $controller = $router->dispatch('get', '/some/missing/route');

        self::assertInstanceOf(RouterFixtureController::class, $controller);
        self::assertSame(['', 'some', 'missing', 'route'], $controller->params);
        // /index 候选命中真实 \Gini\Controller\CGI\Index; 循环内 $path 经 ltrim 落为 'index'
        self::assertSame('index', \Gini\Config::get('runtime.controller_path'));
    }

    public function testViaWithoutRouteRegistersGlobalMiddleware(): void
    {
        $router = new \Gini\CGI\Router();
        $ret = $router->via('mw.a')->via('mw.b');
        self::assertSame($router, $ret);
        self::assertSame(['mw.a', 'mw.b'], $router->toJSON()['middlewares']['*']);


        $router->via('/api/{id}', 'mw.routed');
        $json = $router->toJSON();
        self::assertSame(['mw.routed'], $json['middlewares']['api/([^\\/]+)']);
    }

    public function testFromJSONHydratesRulesAndNestedRouters(): void
    {
        $json = [
            'base' => 'api',
            'options' => ['classPrefix' => '\\Foo\\'],
            'middlewares' => ['*' => ['mw.a']],
            'rules' => [
                'get:users' => [
                    'method' => 'get',
                    'route' => 'users',
                    'dest' => 'User@list',
                    'params' => [],
                ],
                'post:users' => [
                    'method' => 'post',
                    'route' => 'users',
                    'dest' => [
                        'base' => 'nested',
                        'options' => [],
                        'rules' => [],
                    ],
                    'params' => [],
                ],
            ],
        ];

        $router = \Gini\CGI\Router::fromJSON($json);
        $rules = $router->rules();
        self::assertSame('User@list', $rules['get:users']['dest']);
        self::assertInstanceOf(\Gini\CGI\Router::class, $rules['post:users']['dest']);

        // toJSON 往返: 嵌套 Router 再被序列化回数组
        $round = $router->toJSON();
        self::assertSame('api', $round['base']);
        self::assertSame(['classPrefix' => '\\Foo\\'], $round['options']);
        self::assertSame(['mw.a'], $round['middlewares']['*']);
        self::assertSame('nested', $round['rules']['post:users']['dest']['base']);
    }

    public function testCleanUpClearsRulesButKeepsMiddlewares(): void
    {
        $router = new \Gini\CGI\Router();
        $router->via('mw.stay');
        $router->get('/x', '\\' . RouterFixtureController::class . '@show');
        self::assertNotEmpty($router->rules());

        $router->cleanUp();
        self::assertSame([], $router->rules());
        self::assertSame(['mw.stay'], $router->toJSON()['middlewares']['*']);
    }

    public function testSleepListsSerializableProperties(): void
    {
        $router = new \Gini\CGI\Router();
        self::assertSame(['baseRoute', 'rules', 'middlewares'], $router->__sleep());
    }
}

class RouterFixtureController
{
    public $action;
    public $params = [];
    public $middlewares = ['mw.local'];
    public $app;
}
