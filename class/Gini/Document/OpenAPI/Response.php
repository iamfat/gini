<?php

namespace Gini\Document\OpenAPI;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Response
{
    /** @var int */
    public $code;

    /** @var string */
    public $description;

    /** @var \Gini\Document\OpenAPI\MediaType[] */
    public $content;
}
