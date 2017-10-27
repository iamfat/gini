<?php

namespace Gini\PHPUnit\CGI {
    
    require_once __DIR__.'/../gini.php';

    class General extends \Gini\PHPUnit\CLI
    {
        public function testParameters()
        {
            $content = \Gini\CGI::request('hello/parameters/1')->execute()->content();
            $this->assertSame($content, ['1', null, 2]);
        }

        public function testRouter()
        {
            $router = \Gini\CGI::router();
            $router
                ->get('hello/world/{id}', 'Hello@getWorld')
                ->post('hello/world/{id}', 'Hello@postWorld')
                ->any('hello/nested', function ($router) {
                    $router->get('world/{id}', 'Hello@getWorld');
                });

            $content = \Gini\CGI::request('hello/world/1')->execute()->content();
            $this->assertEquals($content['id'], 1);
            $this->assertEquals($content['method'], 'GET');

            $content = \Gini\CGI::request('hello/world/2', ['method'=>'POST'])->execute()->content();
            $this->assertEquals($content['id'], 2);
            $this->assertEquals($content['method'], 'POST');

            $content = \Gini\CGI::request('hello/nested/world/1')->execute()->content();
            $this->assertEquals($content['id'], 1);
            $this->assertEquals($content['method'], 'GET');

            $router->cleanUp();
        }

        public function testArguments()
        {
            $content = \Gini\CGI::request('hello/arguments/1/2/3')->execute()->content();
            $this->assertSame($content, ['1', '2', '3']);
        }

    }

}

namespace Gini\Controller\CGI {
    use \Gini\Controller\REST;
    use \Gini\CGI\Response;
    
    class Hello extends REST
    {
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
