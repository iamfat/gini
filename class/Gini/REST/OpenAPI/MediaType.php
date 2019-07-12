<?php

namespace Gini\REST\OpenAPI;

/**
 * @Annotation
 * @Target({"ANNOTATION"})
 */
class MediaType
{
    /** @var string */
    public $type;

    /** @var \Gini\REST\OpenAPI\Schema */
    public $schema;

    /** @var \Gini\REST\OpenAPI\Example[] */
    public $examples;

    public function toArray()
    {
        $mediaType = [];
        if ($this->schema) {
            $mediaType['schema'] = $this->schema->toArray();
        }
        if ($this->examples) {
            $examples = [];
            foreach ($this->examples as $example) {
                $examples += $example->toArray();
            }
            $mediaType['examples'] = $examples;
        }
        return [$this->type => $mediaType];
    }
}
