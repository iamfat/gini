<?php

namespace Gini\Those;

use Gini\Those;

class AnyOf implements Condition
{
    public function __construct(array $conditions)
    {
        $this->conditions = $conditions;
    }

    public function createWhere(Those $those)
    {
        $whereArr = [];
        foreach ($this->conditions as $condition) {
            $where = $condition->createWhere($those);
            if ($where !== false) {
                $whereArr[] = $where;
            }
        }
        return '(' . join(' OR ', $whereArr) . ')';
    }
}
