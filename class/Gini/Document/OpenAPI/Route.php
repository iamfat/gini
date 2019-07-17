<?php

namespace Gini\Document\OpenAPI;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Route
{
    /** @var string */
    public $path;

    /** @var string[] */
    public $methods = ['get'];
}
