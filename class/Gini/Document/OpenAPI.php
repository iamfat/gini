<?php

namespace Gini\Document;

use Doctrine\Common\Annotations\DocParser;
use Doctrine\Common\Annotations\AnnotationReader;

class OpenAPI
{
    public static function parsePathItem($rm, $pathParameters = [])
    {
        $pathItem = [
            'operationId' => $rm->class . '@' . $rm->name,
            'responses' => [],
            'parameters' => [],
        ];

        $parser = new DocParser();
        $parser->setIgnoreNotImportedAnnotations(true);
        $reader = new AnnotationReader($parser);
        $anns = $reader->getMethodAnnotations($rm);
        foreach ($anns as $ann) {
            if ($ann instanceof \Gini\Document\OpenAPI\Response) {
                $content = [];
                if ($ann->content) {
                    foreach ($ann->content as $mediaType) {
                        $content += $mediaType->toArray();
                    }
                }
                $pathItem['responses'][$ann->code] = [
                    'description' => $ann->description,
                    'content' => $content,
                ];
            }
        }

        $rps = $rm->getParameters();
        $pathItem['parameters'] = array_map(function ($rp) use ($pathParameters) {
            $docParam = [
                'name' => $rp->name,
                'in' => in_array($rp->name, $pathParameters) ? 'path' : 'query',
                'required' => !$rp->isDefaultValueAvailable(),
                'schema' => []
            ];

            if ($rp->hasType()) {
                $docParam['schema']['type'] = (string) $rp->getType();
            }
            if ($rp->isDefaultValueAvailable()) {
                $docParam['schema']['default'] = $rp->getDefaultValue();
            }

            return $docParam;
        }, $rps);

        return $pathItem;
    }

    public static function scan()
    {
        $info = \Gini\Core::moduleInfo(APP_ID);
        $api = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $info->name,
                'description' => $info->description,
                'version' => $info->version
            ],
            // 'servers' => [
            //     ['url' => 'http://xxxx', 'description' => 'Gini Doc'],
            // ],
            'paths' => [],
        ];

        $routerFunc = function ($router) use (&$routerFunc, &$api) {
            foreach ($router->rules() as $key => $rule) {
                if ($rule['dest'] instanceof \Gini\CGI\Router) {
                    $routerFunc($rule['dest']);
                } else {
                    $method = strtolower($rule['method']);
                    $route = '/' . $rule['route'];

                    list($controllerName, $action) = explode('@', $rule['dest'], 2);
                    if (!$action) {
                        $action = $method . 'Default';
                    }

                    try {
                        $rm = new \ReflectionMethod($controllerName, $action);
                        $apiUnit = self::parsePathItem($rm, $rule['params']);
                        $api['paths'][$route][$method] = $apiUnit;
                    } catch (\ReflectionException $e) {
                    }
                }
            }
        };
        $routerFunc(\Gini\CGI::router());

        \Gini\Document::of('Gini\\Controller\\CGI')->filterClasses(function ($rc) {
            return !$rc->isAbstract() && !$rc->isTrait() && !$rc->isInterface() && $rc->isSubClassOf('\\Gini\\Controller\\REST');
        })->filterMethods(function ($rm) {
            return $rm->isPublic() && !$rm->isConstructor()
                && !$rm->isDestructor()
                && preg_match('/^(get|post|delete|put|patch|options)/', $rm->name);
        })->format(function ($unit) use (&$api) {
            if (!preg_match('/^(get|post|delete|put|patch|options)(.+)$/', $unit->method, $matches)) {
                return;
            }

            $method = strtolower($matches[1]);

            $operationId = $unit->class . '@' . $unit->method;
            // 已经定义了路径的, 不再提供原始Controller入口的接口
            foreach ($api['paths'] as $au) {
                if ($au[$method]['operationId'] === $operationId) {
                    return;
                }
            }

            $class = preg_replace('/^' . preg_quote('\\Gini\\Controller\\CGI\\') . '/', '', $unit->class);
            $pathUnits = explode('\\', $class);
            $pathUnits = array_map(function ($u) {
                return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $u));
            }, $pathUnits);
            $pathUnits[] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $matches[2]));

            $route = '/' . implode('/', $pathUnits);

            $apiUnit = self::parsePathItem($unit->reflection->method);
            $api['paths'][$route][$method] = $apiUnit;
        });

        ksort($api['paths']);
        return $api;
    }
}
