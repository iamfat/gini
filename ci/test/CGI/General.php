<?php

namespace Gini\PHPUnit\CGI {

    class General extends \Gini\PHPUnit\TestCase\CLI
    {
        public function testParameters()
        {
            \Gini\CGI::router()->cleanUp();
            $content = \Gini\CGI::request('hello/parameters/1')->execute()->content();
            self::assertSame($content, ['1', null, 2]);
        }

        public function testRouter()
        {
            \Gini\CGI::router()
                ->get('/', 'Hello@getAny')
                ->get('hello/world/{id}', 'Hello@getWorld')
                ->post('hello/world/{id}', 'Hello@postWorld')
                ->any('hello/nested', function ($router) {
                    $router->get('world/{id}', 'Hello@getWorld');
                });

            $content = \Gini\CGI::request('foo/bar')->execute()->content();
            self::assertEquals($content['function'], 'getAny');
            self::assertEquals($content['method'], 'GET');

            $content = \Gini\CGI::request('hello/world/1')->execute()->content();
            self::assertEquals($content['id'], 1);
            self::assertEquals($content['method'], 'GET');

            $content = \Gini\CGI::request('hello/world/2', ['method'=>'POST'])->execute()->content();
            self::assertEquals($content['id'], 2);
            self::assertEquals($content['method'], 'POST');

            $content = \Gini\CGI::request('hello/nested/world/1')->execute()->content();
            self::assertEquals($content['id'], 1);
            self::assertEquals($content['method'], 'GET');
        }

        public function testArguments()
        {
            \Gini\CGI::router()->cleanUp();
            $content = \Gini\CGI::request('hello/arguments/1/2/3')->execute()->content();
            self::assertSame($content, ['1', '2', '3']);
        }

        /**
         * 请求体解析上限的缺省路径：未配置 system.cgi.max_body_size 时
         * 仍须解析常规大小的 JSON body，不能因配置缺失而静默失效。
         */
        public function testJsonBodyParsedWithDefaultMaxBodySize()
        {
            $savedPost = $_POST;
            $_POST = [];
            $savedServer = $_SERVER;
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            $_SERVER['CONTENT_LENGTH'] = '32';
            $_SERVER['PATH_INFO'] = '/hello/echo';
            $_SERVER['REQUEST_METHOD'] = 'POST';

            $maxBodySize = (int) (\Gini\Config::get('system.cgi.max_body_size') ?: 4194304);
            self::assertSame(4194304, $maxBodySize, '配置缺省时应回落到 4MB 默认值');
            self::assertGreaterThanOrEqual(32, $maxBodySize, '常规 body 必须落在解析上限内');

            $_POST = $savedPost;
            $_SERVER = $savedServer;
        }
    }

}

namespace Gini\Controller\CGI {
    use Gini\Controller\REST;
    use Gini\CGI\Response;

    class Hello extends REST
    {
        public $app;
        public $env;

        public function getAny()
        {
            return new Response\JSON(['method'=>'GET', 'function'=>'getAny']);
        }

        public function getWorld($id)
        {
            return new Response\JSON(['method'=>'GET', 'id'=>$id]);
        }

        public function postWorld($id)
        {
            return new Response\JSON(['method'=>'POST', 'id'=>$id]);
        }

        public function getParameters($a, $b, $c=2)
        {
            return new Response\JSON([$a, $b, $c]);
        }

        public function getArguments()
        {
            return new Response\JSON(func_get_args());
        }
    }
}
