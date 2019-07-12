<?php

namespace Gini\REST\OpenAPI;

/**
 * @Annotation
 * @Target({"ANNOTATION"})
 */
class Example
{
    /** @var mixed */
    public $value;

    /** @var string */
    public $key;

    /** @var string */
    public $summary;

    /** @var string */
    public $description;

    public function toArray()
    {
        if (!$this->key) $this->key = uniqid();
        return [
            $this->key => [
                'summary' => $this->summary,
                'description' => $this->description,
                'value' => $this->value
            ]
        ];
    }
}
