<?php

namespace Gini\REST\OpenAPI;

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

    /** @var \Gini\REST\OpenAPI\MediaType[] */
    public $content;
}
