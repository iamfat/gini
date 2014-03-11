<?php

namespace Gini\Database;

/**
 * Database statement return by Database::query()
 *
 * @package Gini
 * @author Jia Huang
 **/
class Statement
{
    private $_pdo_st;

    public function __construct($pdo_st)
    {
        $this->_pdo_st = $pdo_st;
    }

    public function row($style=\PDO::FETCH_OBJ)
    {
        return $this->_pdo_st->fetch($style);
    }

    public function rows($style=\PDO::FETCH_OBJ)
    {
        return $this->_pdo_st->fetchAll($style);
    }

    public function count()
    {
        return $this->_pdo_st->rowCount();
    }

    public function value()
    {
        $r = $this->row(\PDO::FETCH_NUM);
        if (!$r) return null;
        return $r[0];
    }

} // END class
