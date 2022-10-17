<?php

namespace Gini\Database;

class SQL
{
    private $_SQL;
    private $_sep;

    public function __construct($SQL, $sep = ',')
    {
        $this->_SQL = $SQL;
        $this->_sep = $sep;
    }

    public function __toString()
    {
        if (is_array($this->_SQL)) {
            return join($this->_sep, $this->_SQL);
        }
        return strval($this->_SQL);
    }
}
