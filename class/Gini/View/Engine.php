<?php

namespace Gini\View;

interface Engine
{
    public function __construct($path, array $vars);
    public function __toString();
}
