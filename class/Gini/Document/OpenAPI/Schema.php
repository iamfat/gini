<?php

namespace Gini\Document\OpenAPI;

/**
 * @Annotation
 * @Target({"ANNOTATION"})
 */
class Schema
{
    /** @var string */
    public $type;

    public function toArray()
    {
        return [
            'type' => $this->type,
        ];
    }
}
