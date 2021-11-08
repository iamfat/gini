<?php

namespace Gini\Database;

class SQL
{
    private $_SQL;

    public function __construct($SQL)
    {
        $this->_SQL = strval($SQL);
    }

    public function __toString()
    {
        return $this->_SQL;
    }
}
