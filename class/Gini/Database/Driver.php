<?php

namespace Gini\Database;

/**
 * Database Driver Interface.
 *
 * @author Jia Huang
 **/
interface Driver
{
    public function quoteIdent($s);

    /** Whether a transaction is currently active (PDO-compatible semantics). */
    public function inTransaction();

    public function tableExists($table);
    public function tableStatus($table);
    public function tableSchema($name, $refresh);

    public function createTable($table);
    public function adjustTable($table, $schema, $flag = 0);
    public function dropTable($table);

    public function emptyDatabase();

    // function snapshot($filename, $tbls);
    // function restore($filename, &$restore_filename, $tables);

    public function diagnose();
} // END interface
