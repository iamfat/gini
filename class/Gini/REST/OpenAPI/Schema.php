<?php

namespace Gini\REST\OpenAPI;

/**
 * @Annotation
 * @Target({"ANNOTATION"})
 */
class Schema
{
    /** @var string */
    public $type;

    public function toArray() {
        return [
            'type' => $this->type,
        ];
    }
}
