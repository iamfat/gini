<?php

namespace Gini\Controller\CLI;

use \Doctrine\Common\Annotations\AnnotationReader;
use \Doctrine\Common\Annotations\AnnotationRegistry;

AnnotationRegistry::registerLoader(function($class) {
    return \Gini\Core::autoload($class);
});

class Doc extends \Gini\Controller\CLI
{
    public function actionAPI($args)
    {
        $doc = \Gini\Document::of('Gini\\Controller\\API')->filterClasses(function ($rc) {
            return !$rc->isAbstract() && !$rc->isTrait() && !$rc->isInterface();
        })->filterMethods(function ($rm) {
            return $rm->isPublic() && !$rm->isConstructor()
                && !$rm->isDestructor()
                && preg_match('/^action/', $rm->name);
        })->format();
    }

    public function actionCLI($args)
    {
        $doc = \Gini\Document::of('Gini\\Controller\\CLI')->filterClasses(function ($rc) {
            return !$rc->isAbstract() && !$rc->isTrait() && !$rc->isInterface();
        })->filterMethods(function ($rm) {
            return $rm->isPublic() && !$rm->isConstructor()
                && !$rm->isDestructor()
                && preg_match('/^action/', $rm->name);
        })->format(function ($unit) {
            $class = preg_replace('/^' . preg_quote('\\Gini\\Controller\\CLI\\') . '/', '', $unit->class);
            $classUnits = explode('\\', $class);
            $classUnits = array_map(function ($u) {
                return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $u));
            }, $classUnits);

            $args = array_merge(['gini'], $classUnits);
            $args[] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', preg_replace('/^action/', '', $unit->method)));
            echo implode(' ', $args) . "\n";
        });
    }

    public function actionCGI($args)
    {
        $doc = \Gini\Document::of('Gini\\Controller\\CGI')->filterClasses(function ($rc) {
            return !$rc->isAbstract() && !$rc->isTrait() && !$rc->isInterface() && $rc->isSubClassOf('\\Gini\\Controller\\CGI');
        })->filterMethods(function ($rm) {
            return $rm->isPublic() && !$rm->isConstructor()
                && !$rm->isDestructor()
                && ($rm->name == '__index' || preg_match('/^action/', $rm->name));
        })->format(function ($unit) {
            $class = preg_replace('/^' . preg_quote('\\Gini\\Controller\\CGI\\') . '/', '', $unit->class);
            $pathUnits = explode('\\', $class);
            $pathUnits = array_map(function ($u) {
                return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $u));
            }, $pathUnits);

            if ($unit->method !== '__index') {
                $pathUnits[] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', preg_replace('/^action/', '', $unit->method)));
            }

            if (count($unit->params) > 0) {
                foreach ($unit->params as $param) {
                    $docParam = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', strtr($param['name'], '_', '-')));
                    if (isset($param['default'])) {
                        $docParam .= '=' . strval($param['default']);
                    }
                    $pathUnits[] = '{' . $docParam . '}';
                }
            }

            echo 'REQUEST ' . implode('/', $pathUnits) . "\n";
        });
    }

    public function actionOpenAPI($args)
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

                    $apiUnit = [
                        'operationId' => $rule['dest'],
                        'responses' => []
                    ];

                    try {
                        $rm = new \ReflectionMethod($controllerName, $action);
                        $reader = new AnnotationReader();
                        $anns = $reader->getMethodAnnotations($rm);
                        foreach ($anns as $ann) {
                            if ($ann instanceof \Gini\REST\OpenAPI\Response) {
                                $content = [];
                                if ($ann->content) foreach ($ann->content as $mediaType) {
                                    $content += $mediaType->toArray();
                                }
                                $apiUnit['responses'][$ann->code] = [
                                    'description' => $ann->description,
                                    'content' => $content,
                                ];
                            }
                        }
                        $rps = $rm->getParameters();
                        $apiUnit['parameters'] = array_map(function ($rp) use ($rule) {
                            $docParam = [
                                'name' => $rp->name,
                                'in' => in_array($rp->name, $rule['params']) ? 'path' : 'query',
                                'required' => !$rp->isDefaultValueAvailable(),
                                'schema' => []
                            ];

                            if ($rp->hasType()) {
                                $docParam['schema']['type'] = $rp->getType();
                            }
                            if ($rp->isDefaultValueAvailable()) {
                                $docParam['schema']['default'] = $rp->getDefaultValue();
                            }

                            return $docParam;
                        }, $rps);
                    } catch (\ReflectionException $e) { }

                    $api['paths'][$route][$method] = $apiUnit;
                }
            }
        };
        $routerFunc(\Gini\CGI::router());

        \Gini\Document::of('Gini\\Controller\\CGI')->filterClasses(function ($rc) {
            return !$rc->isAbstract() && !$rc->isTrait() && !$rc->isInterface() && $rc->isSubClassOf('\\Gini\\Controller\\REST');
        })->filterMethods(function ($rm) {
            return $rm->isPublic() && !$rm->isConstructor()
                && !$rm->isDestructor()
                && preg_match('/^(get|post|delete|put|options)/', $rm->name);
        })->format(function ($unit) use (&$api) {
            $class = preg_replace('/^' . preg_quote('\\Gini\\Controller\\CGI\\') . '/', '', $unit->class);
            $pathUnits = explode('\\', $class);
            $pathUnits = array_map(function ($u) {
                return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $u));
            }, $pathUnits);

            if (preg_match('/^(get|post|delete|put|options)(.+)$/', $unit->method, $matches)) {
                $method = strtolower($matches[1]);
                $pathUnits[] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $matches[2]));
                $route = '/' . implode('/', $pathUnits);

                $operationId = $unit->class . '@' . $unit->method;

                // 已经定义了路径的, 不再提供原始Controller入口的接口
                foreach ($api['paths'] as $au) {
                    if ($au[$method]['operationId'] === $operationId) {
                        return;
                    }
                }

                $apiUnit = [
                    'operationId' => $unit->class . '@' . $unit->method,
                    'parameters' => [],
                    'responses' => []
                ];

                if (count($unit->params) > 0) {
                    foreach ($unit->params as $param) {
                        $docParam = [
                            'name' => $param['name'],
                            'in' => 'query',
                            'required' => !isset($param['default']),
                            'schema' => []
                        ];

                        if (isset($param['default'])) {
                            $docParam['schema']['default'] = $param['default'];
                        }

                        $apiUnit['parameters'][] = $docParam;
                    }
                }

                $api['paths'][$route][$method] = $apiUnit;
            }
        });

        ksort($api['paths']);
        echo json_encode($api, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
