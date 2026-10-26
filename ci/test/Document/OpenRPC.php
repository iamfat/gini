<?php

namespace Gini\Controller\API\UtDoc {

    class Thing extends \Gini\Controller\API
    {
        public function actionFetchList()
        {
        }
    }
}

namespace Gini\PHPUnit\Document {

    class OpenRPC extends \Gini\PHPUnit\TestCase\CLI
    {
        // dummy action used as a reflection target; intentionally no docblock
        public function actionGetUserInfo()
        {
        }

        /**
         * Fetch list summary
         *
         * Description paragraph here.
         */
        public function actionUtDoced(int $id, $name = 'x')
        {
        }

        /**
         * Ends with a dot.
         *
 * This description is lost with current lexer behavior.
         */
        public function actionUtDotSummary()
        {
        }

        public function actionGet_user_info()
        {
        }

        public function testCamelizeMethodName()
        {
            $openrpc = new \Gini\Document\OpenRPC();
            $item = $openrpc->parseMethodItem(new \ReflectionMethod($this, 'actionGetUserInfo'));

            self::assertIsArray($item);
            self::assertStringEndsWith('getUserInfo', $item['name']);
        }

        public function testParseMethodItemExtractsSummaryDescriptionAndParams()
        {
            $openrpc = new \Gini\Document\OpenRPC();
            $item = $openrpc->parseMethodItem(new \ReflectionMethod($this, 'actionUtDoced'));

            self::assertSame('Fetch list summary', $item['summary']);
            self::assertSame('Description paragraph here.', $item['description']);
            self::assertNull($item['result']);
            self::assertSame([
                [
                    'name' => 'id',
                    'required' => true,
                    'schema' => ['type' => 'int'],
                ],
                [
                    'name' => 'name',
                    'required' => false,
                    'schema' => ['default' => 'x'],
                ],
            ], $item['params']);
        }

        public function testParseMethodItemSummaryEndingWithDotSuppressesDescription()
        {
            // 现状钉扎: summary 以句号结尾时, fetchPlainText 的句号断行分支
            // 使第二个段落(描述)永远不被捕获
            $openrpc = new \Gini\Document\OpenRPC();
            $item = $openrpc->parseMethodItem(new \ReflectionMethod($this, 'actionUtDotSummary'));

            self::assertSame('Ends with a dot.', $item['summary']);
            self::assertArrayNotHasKey('description', $item);
        }

        public function testParseMethodItemCamelizesSnakeCaseMethodName()
        {
            $openrpc = new \Gini\Document\OpenRPC();
            $item = $openrpc->parseMethodItem(new \ReflectionMethod($this, 'actionGet_user_info'));

            self::assertStringEndsWith('.getUserInfo', $item['name']);
        }

        public function testParseMethodItemStripsApiControllerNamespacePrefix()
        {
            $openrpc = new \Gini\Document\OpenRPC();
            $item = $openrpc->parseMethodItem(
                new \ReflectionMethod(\Gini\Controller\API\UtDoc\Thing::class, 'actionFetchList')
            );

            // Gini\Controller\API\ 前缀剥除, 命名空间分隔符转点
            self::assertSame('UtDoc.Thing.fetchList', $item['name']);
        }
    }
}
