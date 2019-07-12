<?php

namespace Gini\Document;

use \Doctrine\Common\Annotations\AnnotationReader;
use \Doctrine\Common\Annotations\AnnotationRegistry;
use \Doctrine\Common\Inflector\Inflector;

AnnotationRegistry::registerUniqueLoader(function ($class) {
    return \Gini\Core::autoload($class);
});

class OpenRPC
{
    public static function parseMethodItem($rm)
    {
        if (!preg_match('/^(action|)(.+)$/', $rm->name, $matches)) {
            return;
        }

        $class = preg_replace('/^' . preg_quote('Gini\\Controller\\API\\') . '/', '', $rm->class);

        $name = strtr($class, ['\\' => '.']) . '.'. Inflector::camelize($matches[2]);

        $methodItem = [
            'name' => $name,
            'params' => [],
            'result' => null,
        ];

        // $reader = new AnnotationReader();
        // $anns = $reader->getMethodAnnotations($rm);
        // foreach ($anns as $ann) {
        //     if ($ann instanceof \Gini\REST\OpenAPI\Response) {
        //         $content = [];
        //         if ($ann->content) foreach ($ann->content as $mediaType) {
        //             $content += $mediaType->toArray();
        //         }
        //         $pathItem['responses'][$ann->code] = [
        //             'description' => $ann->description,
        //             'content' => $content,
        //         ];
        //     }
        // }

        $rps = $rm->getParameters();
        $methodItem['params'] = array_map(function ($rp) {
            $paramItem = [
                'name' => $rp->name,
                'required' => !$rp->isDefaultValueAvailable(),
                'schema' => []
            ];

            if ($rp->hasType()) {
                $paramItem['schema']['type'] = (string) $rp->getType();
            }
            if ($rp->isDefaultValueAvailable()) {
                $paramItem['schema']['default'] = $rp->getDefaultValue();
            }

            return $paramItem;
        }, $rps);

        return $methodItem;
    }

    public static function scan()
    {
        $info = \Gini\Core::moduleInfo(APP_ID);
        $api = [
            'openrpc' => '1.0.0-rc1',
            'info' => [
                'title' => $info->name,
                'description' => $info->description,
                'version' => $info->version
            ],
            'servers' => [
                ['url' => URL('api'), 'name' => $info->name],
            ],
            'methods' => [],
        ];

        \Gini\Document::of('\\Gini\\Controller\\API')->filterClasses(function ($rc) {
            return !$rc->isAbstract() && !$rc->isTrait() && !$rc->isInterface();
        })->filterMethods(function ($rm) {
            return $rm->isPublic() && !$rm->isConstructor()
                && !$rm->isDestructor()
                && preg_match('/^action/', $rm->name);
        })->format(function ($unit) use (&$api) {
            print_r($unit->name);
            $methodItem = self::parseMethodItem($unit->reflection->method);
            $api['methods'][] = $methodItem;
        });

        ksort($api['methods']);
        return $api;
    }
}
