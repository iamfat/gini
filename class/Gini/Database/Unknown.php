<?php

namespace Gini\Database;

class Unknown implements Driver
{
    public function __construct()
    {
        // DO NOTHING
    }

    public function quoteIdent($s)
    {
        return '"' . addslashes($s) . '"';
    }

    public function tableExists($table)
    {
        return false;
    }

    public function tableStatus($table)
    {
        return false;
    }

    public function adjustTable($table, $schema, $flag = 0)
    {
    }

    public function tableSchema($name, $refresh = false)
    {
        return ['fields' => [], 'indexes' => []];
    }

    public function createTable($table, $engine = null)
    {
        return false;
    }

    public function dropTable($table)
    {
        return false;
    }

    public function emptyDatabase()
    {
        return false;
    }

    public function diagnose()
    {
    }
}
