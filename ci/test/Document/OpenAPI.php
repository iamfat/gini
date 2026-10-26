<?php

namespace Gini\PHPUnit\Document;

class OpenAPI extends \Gini\PHPUnit\TestCase\Unit
{
    public function testParsePathItemReadsResponseAnnotation()
    {
        $rm = new \ReflectionMethod(OpenAPIFixtureAnnotated::class, 'getItems');
        $item = \Gini\Document\OpenAPI::parsePathItem($rm, ['id']);

        self::assertSame(
            OpenAPIFixtureAnnotated::class . '@getItems',
            $item['operationId']
        );
        self::assertSame([
            200 => [
                'description' => 'OK',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ],
        ], $item['responses']);
        self::assertSame([
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'int'],
            ],
            [
                'name' => 'tag',
                'in' => 'query',
                'required' => false,
                'schema' => ['default' => 'x'],
            ],
        ], $item['parameters']);
    }

    public function testParsePathItemWithoutAnnotationsDefaultsToEmptyResponses()
    {
        $rm = new \ReflectionMethod(OpenAPIFixturePlain::class, 'postItem');
        $item = \Gini\Document\OpenAPI::parsePathItem($rm);

        self::assertSame(OpenAPIFixturePlain::class . '@postItem', $item['operationId']);
        self::assertSame([], $item['responses']);
        // 无 pathParameters 时全部参数归入 query
        self::assertSame('query', $item['parameters'][0]['in']);
        self::assertSame('query', $item['parameters'][1]['in']);
        self::assertTrue($item['parameters'][0]['required']);
        self::assertTrue($item['parameters'][1]['required']);
        self::assertSame([], $item['parameters'][0]['schema']);
    }
}

class OpenAPIFixtureAnnotated
{
    /**
     * @\Gini\Document\OpenAPI\Response(
     *     code=200,
     *     description="OK",
     *     content={
     *         @\Gini\Document\OpenAPI\MediaType(
     *             type="application/json",
     *             schema=@\Gini\Document\OpenAPI\Schema(type="object")
     *         )
     *     }
     * )
     */
    public function getItems(int $id, $tag = 'x')
    {
    }
}

class OpenAPIFixturePlain
{
    public function postItem($a, $b)
    {
    }
}
