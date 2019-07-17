<?php

namespace Gini\Document;

use \Doctrine\Common\Annotations\AnnotationReader;
use \Doctrine\Common\Annotations\AnnotationException;
use \Doctrine\Common\Annotations\AnnotationRegistry;
use \Doctrine\Common\Inflector\Inflector;

use \Gini\Document\OpenRPC\DocLexer;

AnnotationRegistry::registerUniqueLoader(function ($class) {
    return \Gini\Core::autoload($class);
});

class OpenRPC
{
    private $lexer;

    public function __construct()
    {
        $this->lexer = new DocLexer;
    }

    public function fetchPlainText()
    {
        $paragraph = '';
        do {
            $peek = $this->lexer->glimpse();
            if ($peek === null) {
                continue;
            }

            if ($peek['type'] === DocLexer::T_AT) {
                break;
            }

            if ($peek['type'] === DocLexer::T_NEWLINE) {

                while ($this->lexer->isNextToken(DocLexer::T_SPACE)) {
                    if (!$this->lexer->moveNext()) {
                        break;
                    }
                }

                if ($this->lexer->isNextToken(DocLexer::T_NEWLINE)) {
                    while ($this->lexer->isNextToken(DocLexer::T_NEWLINE)) {
                        if (!$this->lexer->moveNext()) {
                            break;
                        }
                    }
                    break;
                }

                if (substr($paragraph, -1) == '.') {
                    $this->lexer->moveNext();
                    break;
                }

                $paragraph = trim($paragraph);
                continue;
            }

            $paragraph .= $peek['value'];
        } while ($this->lexer->moveNext());

        return trim($paragraph);
    }

    public function parseMethodItem($rm)
    {
        if (!preg_match('/^(action|)(.+)$/', $rm->name, $matches)) {
            return;
        }

        $class = preg_replace('/^' . preg_quote('Gini\\Controller\\API\\') . '/', '', $rm->class);
        $name = strtr($class, ['\\' => '.']) . '.' . Inflector::camelize($matches[2]);

        $methodItem = [
            'name' => $name,
            'params' => [],
            'result' => null,
        ];

        $comment = $rm->getDocComment();
        if ($comment) {
            // PHPDoc基本格式: https://docs.phpdoc.org/references/phpdoc/basic-syntax.html
            $this->lexer->setInput(trim($comment, '* /'));

            $summary = $this->fetchPlainText();
            if ($summary) {
                $description = $this->fetchPlainText();
            } else {
                $description = null;
            }

            if ($summary) {
                $methodItem['summary'] = $summary;
            }

            if ($description) {
                $methodItem['description'] = $description;
            }

            // TODO: 解析@return, @param来丰富文档
            // DocLexer::T_OPEN_PARENTHESIS | DocLexer::T_CLOSE_PARENTHESIS)
        }

        $reader = new AnnotationReader();
        try {
            $anns = $reader->getMethodAnnotations($rm);
            foreach ($anns as $ann) {
                // if ($ann instanceof \Gini\REST\OpenAPI\Response) {
                //     $content = [];
                //     if ($ann->content) foreach ($ann->content as $mediaType) {
                //         $content += $mediaType->toArray();
                //     }
                //     $pathItem['responses'][$ann->code] = [
                //         'description' => $ann->description,
                //         'content' => $content,
                //     ];
                // }
                print_r($ann);
            }
        } catch (AnnotationException $e) { }


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

        $openRPC = new OpenRPC();

        \Gini\Document::of('\\Gini\\Controller\\API')->filterClasses(function ($rc) {
            return !$rc->isAbstract() && !$rc->isTrait() && !$rc->isInterface();
        })->filterMethods(function ($rm) {
            return $rm->isPublic() && !$rm->isConstructor()
                && !$rm->isDestructor()
                && preg_match('/^action/', $rm->name);
        })->format(function ($unit) use (&$api, $openRPC) {
            $methodItem = $openRPC->parseMethodItem($unit->reflection->method);
            $api['methods'][] = $methodItem;
        });

        ksort($api['methods']);
        return $api;
    }
}
