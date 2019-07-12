<?php

namespace Gini\Controller\CLI;

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
        $api = \Gini\Document\OpenAPI::scan();
        echo json_encode($api, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
