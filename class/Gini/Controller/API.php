<?php

/**
 * JSON-RPC API Controller.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2014-02-08
 **/

/**
 * Define DocBlock.
 **/

namespace Gini\Controller;

abstract class API
{
    /**
     * current app module info.
     *
     * @var object
     */
    public $app;

    /**
     * Environmental variables passed to current controller.
     *
     * @var array
     */
    public $env;
}
