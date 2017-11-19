<?php

namespace Gini\PHPUnit\REST {

    class Remote extends \Gini\PHPUnit\TestCase\CLI
    {
        public function testDefault()
        {
            \Gini\IoC::bind('\Gini\HTTP', function(){
                $http = $this->getMockBuilder('\Gini\HTTP')
                    ->setMockClassName('MOBJ_'.uniqid())
                    ->setMethods(['request'])
                    ->getMock();

                $http->expects($this->any())
                ->method('request')
                ->will($this->returnCallback(function ($method, $url, $query, $timeout) {
                    if ($url == 'http://localhost/rest/hello/article/1') {
                        $html = <<<HTML
HTTP/1.1 200 OK
Date: Sat, 28 Nov 2009 04:36:25 GMT
Content-Type: application/json; charset=UTF-8

{"hello":"world"}
HTML;
                        return new \Gini\HTTP\Response($html);
                    } elseif ($url == 'http://localhost/rest/hello/exception') {
                        $html = <<<HTML
HTTP/1.1 401 Unauthorized
Date: Sat, 28 Nov 2009 04:36:25 GMT
Content-Type: application/json; charset=UTF-8

{"hello":"401"}
HTML;
                        return new \Gini\HTTP\Response($html);
                    }
                }));

                return $http;
            });

            $rest = new \Gini\REST('http://localhost/rest');
            $data = $rest->get('hello/article/1');
            $this->assertEquals($data['hello'], 'world');

            try {
                $data = $rest->get('hello/exception');
                $this->assertEquals($data['hello'], '401'); 
            } catch (\Gini\REST\Exception $e) {
                $data = $e->getData();
                $this->assertEquals($data['hello'], '401');                
            }
            
            \Gini\IoC::clear('\Gini\HTTP');
        }
    }

}
