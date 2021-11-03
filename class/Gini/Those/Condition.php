<?php

namespace Gini\Those;

use Gini\Those;

interface Condition
{
    public function createWhere(Those $those);
}
