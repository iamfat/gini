<?php

namespace Gini\PHPUnit\REST {

    class Remote extends \Gini\PHPUnit\TestCase\CLI
    {
        public function setUp(): void
        {
            parent::setUp();
            \Gini\IoC::bind('\Gini\HTTP', $this->getHttp());
        }

        public function tearDown(): void
        {
            \Gini\IoC::clear('\Gini\HTTP');
            parent::tearDown();
        }

        public function testInit(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                // 为了保证百分百覆盖率增加的额外断言
                $data = $rest->__call('__call', '');
                self::assertNull($data);
            } catch (\Exception $e) {
                self::fail();
            }
            self::assertTrue(true);
        }

        /**
         * 通过 of 成功创建 REST 实例, 并且可以成功发起请求, 则测试通过
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testOf(): void
        {
            try {
                $rest = \Gini\REST::of('http://localhost/rest');
                self::assertInstanceOf(\Gini\REST::class, $rest);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 通过 get 方法获取到正确的返回值，说明请求成功
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testGet(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $data = $rest->get('article', ['hello' => 'world']);
                self::assertEquals('world', $data['hello']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 通过 post 方法发送请求,如果接口返回的结果和传入参数一致，说明请求成功
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testPost(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $data = $rest->post('article', ['hello' => 'world']);
                self::assertEquals('world', $data['hello']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 通过 put 方法发送请求,如果接口返回的结果和传入参数一致，说明请求成功
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testPut(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $data = $rest->put('article/1', ['hello' => 'hello world']);
                self::assertEquals('hello world', $data['hello']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         *  通过 patch 方法发送请求,如果接口返回的结果和传入参数一致，说明请求成功
         *  如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testPatch(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $data = $rest->patch('article/1', ['hello' => 'world']);
                self::assertEquals('world', $data['hello']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 通过 delete 方法发送请求,如果接口返回为 true，说明请求成功
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testDelete(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $data = $rest->delete('article/1');
                self::assertTrue($data['status']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 请求 enableCookie 接口，如果返回 true 则说明启用了 cookie
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testEnableCookie(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $rest->enableCookie();
                $data = $rest->get('cookie');
                self::assertTrue($data['enableCookie']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 请求 disableCookie 接口，如果返回 true 则说明启用了 cookie
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testDisableCookie(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $rest->disableCookie();
                $data = $rest->get('cookie');
                self::assertFalse($data['enableCookie']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 请求 header 接口 如果请求结果中 hello 的值为 world 则 header 设置成功
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testHeader(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $rest->header('hello', 'world');
                $data = $rest->get('header');
                self::assertEquals('world', $data['hello']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         *  请求 header 接口 如果请求结果中 content-type 的值为 json 则 header 设置成功
         *  如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testJson(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $rest->json();
                $data = $rest->get('header');
                self::assertEquals('application/json', $data['Content-Type']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 请求 header 接口 如果请求结果中 content-type 的值为 json 则 header 设置成功
         * 如果捕获到异常, 说明请求失败
         * @return void
         */
        public function testForm(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $rest->form();
                $data = $rest->get('header');
                self::assertEquals('application/x-www-form-urlencoded', $data['Content-Type']);
            } catch (\Gini\REST\Exception|\Exception $e) {
                self::fail();
            }
        }

        /**
         * 请求一个不存在的接口, 如果捕获到异常, 并且 getData 获取到正确的 response 则测试通过
         * 其他情况 测试不通过
         * @return void
         */
        public function testException(): void
        {
            try {
                $rest = new \Gini\REST('http://localhost/rest');
                $rest->get('exception');
            } catch (\Gini\REST\Exception $e) {
                self::assertInstanceOf(\Gini\REST\Exception::class, $e);
                self::assertEquals('您访问的页面不存在', $e->getData()['msg']);
                return;
            } catch (\Exception $e) {
                self::fail();
            }
            self::fail();
        }

        public function testGetThrowsRestExceptionOn404(): void
        {
            $this->bindRawResponse("HTTP/1.1 404 Not Found\nContent-Type: application/json\n\n{\"msg\": \"gone\"}");
            $rest = new \Gini\REST('http://localhost/rest');

            try {
                $rest->get('missing');
                self::fail('4xx 响应应抛出 REST\\Exception');
            } catch (\Gini\REST\Exception $e) {
                self::assertSame(404, (int) $e->getCode());
                self::assertSame('Not Found', $e->getMessage());
                // 异常携带解码后的响应体
                self::assertSame(['msg' => 'gone'], $e->getData());
            }
        }

        public function testPostThrowsRestExceptionOn500WithUndecodableBody(): void
        {
            $this->bindRawResponse("HTTP/1.1 500 Internal Server Error\nContent-Type: text/html\n\n<html>oops</html>");
            $rest = new \Gini\REST('http://localhost/rest');

            try {
                $rest->post('article', ['a' => 1]);
                self::fail('5xx 响应应抛出 REST\\Exception');
            } catch (\Gini\REST\Exception $e) {
                self::assertSame(500, (int) $e->getCode());
                self::assertSame('Internal Server Error', $e->getMessage());
                // 非 JSON 响应体 → data 为 null
                self::assertNull($e->getData());
            }
        }

        public function testBadRequestExceptionCarriesDecodedPayload(): void
        {
            $this->bindRawResponse("HTTP/1.1 400 Bad Request\n\n{\"error\": \"invalid\", \"details\": [1, 2]}");
            $rest = new \Gini\REST('http://localhost/rest');

            try {
                $rest->delete('article/9');
                self::fail('400 响应应抛出 REST\\Exception');
            } catch (\Gini\REST\Exception $e) {
                self::assertSame(400, (int) $e->getCode());
                self::assertSame('Bad Request', $e->getMessage());
                self::assertSame(['error' => 'invalid', 'details' => [1, 2]], $e->getData());
            }
        }

        public function testBadJsonOn2xxReturnsNull(): void
        {
            $this->bindRawResponse("HTTP/1.1 200 OK\nContent-Type: text/plain\n\nnot-a-json-body");
            $rest = new \Gini\REST('http://localhost/rest');

            // 2xx + 坏 JSON: @json_decode 抑制错误, 原样返回 null, 不抛异常
            self::assertNull($rest->get('article'));
        }

        public function testUnsupportedMethodReturnsNull(): void
        {
            $rest = new \Gini\REST('http://localhost/rest');

            // 不在 supportedMethods 白名单: __call 无返回值
            self::assertNull($rest->frobnicate('article'));
        }

        /**
         * 以给定原始 HTTP 报文绑定 \Gini\HTTP 替身, 用于异常分支注入
         */
        private function bindRawResponse(string $raw): void
        {
            \Gini\IoC::bind('\Gini\HTTP', function () use ($raw) {
                $http = $this->getMockBuilder('\Gini\HTTP')
                    ->setMockClassName('MOBJ_' . uniqid())
                    ->getMock();
                $http->expects($this->any())
                    ->method('request')
                    ->will($this->returnCallback(function () use ($raw) {
                        return new \Gini\HTTP\Response($raw);
                    }));

                return $http;
            });
        }

        /**
         * REST 类发送请求, 依赖底层的 HTTP 类。 这里模拟了 HTTP 类, 假设进行了实际的 http 请求并且对请求时的相关方法进行了调用
         * 这里提供了四个接口
         * http://localhost/rest/article 一个 restFul 接口 并且模拟有一条数据 ['hello'=>'world]
         * http://localhost/rest/cookie 返回是否调用了 disableCookie enableCookie 方法
         * http://localhost/rest/header 返回 header
         *
         * @return \Closure
         */
        private function getHttp(): \Closure
        {
            return function () {
                $header = [];
                $body = [
                    ['hello' => 'world']
                ];
                $http = $this->getMockBuilder('\Gini\HTTP')
                    ->setMockClassName('MOBJ_' . uniqid())
                    ->getMock();
                $http->expects($this->any())
                    ->method('disableCookie')
                    ->will($this->returnCallback(function () use (&$body) {
                        $body = [];
                        $body['enableCookie'] = false;
                    }));
                $http->expects($this->any())
                    ->method('enableCookie')
                    ->will($this->returnCallback(function () use (&$body) {
                        $body = [];
                        $body['enableCookie'] = true;
                    }));
                $http->expects($this->any())
                    ->method('header')
                    ->will($this->returnCallback(function ($name, $value) use (&$header) {
                        $header[$name] = $value;
                    }));
                $http->expects($this->any())
                    ->method('request')
                    ->will($this->returnCallback(function ($method, $url, $query, $timeout) use (&$header, &$body) {
                        if (
                            $url != 'http://localhost/rest/article' &&
                            $url != 'http://localhost/rest/article/1' &&
                            $url != 'http://localhost/rest/cookie' &&
                            $url != 'http://localhost/rest/header'
                        ) {
                            $html = <<<HTML
HTTP/1.1 404 Not Found
Date: Sat, 28 Nov 2009 04:36:25 GMT
Content-Type: application/json; charset=UTF-8

{"msg": "您访问的页面不存在"}
HTML;
                            return new \Gini\HTTP\Response($html);
                        }
                        if ($url == 'http://localhost/rest/article') {
                            if ($method == 'post') {
                                if (empty($query)) $res = ['status' => false];
                                else {
                                    $body[] = $query;
                                    $res = json_encode($query);
                                }
                            } elseif ($method == 'get') {
                                foreach ($body as $key => $value) {
                                    foreach ($query as $k => $v) {
                                        if ($value[$k] == $v) {
                                            $res = json_encode($value);
                                            break 2;
                                        }
                                    }
                                }
                            }
                        } elseif ($url == 'http://localhost/rest/article/1') {
                            if (in_array($method, ['patch', 'put'])) {
                                if (empty($query)) $res = ['status' => false];
                                else {
                                    $body[0] = $query;
                                    $res = json_encode($body[0]);
                                }
                            } elseif ($method == 'delete') {
                                if (!isset($body[0])) $res = ['status' => false];
                                else {
                                    unset($body[0]);
                                    $res = json_encode(['status' => true]);
                                }
                            }
                        } elseif ($url == 'http://localhost/rest/cookie' || $url == 'http://localhost/rest/cookie') {
                            $res = json_encode($body);
                        } elseif ($url == 'http://localhost/rest/header') {
                            $body = $header;
                            $res = json_encode($body);
                        }
                        foreach ($header as $key => $val) {
                            $headerStr = $key . ': ' . $val . "\r\n";
                        }
                        $html = <<<HTML
HTTP/1.1 200 OK
Date: Sat, 28 Nov 2009 04:36:25 GMT
$headerStr

$res
HTML;
                        return new \Gini\HTTP\Response($html);
                    }));
                return $http;
            };
        }
    }
}
