<?php

/**
 * Database Abstract Layer
 *
 * @author Jia Huang
 * @version $Id$
 * @copyright Genee, 2014-01-27
 **/

/**
 * Define DocBlock
 **/

namespace Gini;

/**
 * Database Abstract Layer
 *
 * @package Gini
 * @author Jia Huang
 **/
class Database
{
    /**
     * Loaded databases;
     *
     * @var array
     **/
    public static $DB = array();

    private $_driver;

    /**
     * Get a database object by name
     * @param  string|null $name Name of the database configured in database.yml
     * @return object
     **/
    public static function db($name=null)
    {
        $name = $name ?: 'default';
        if (!isset(self::$DB[$name])) {

            $opt = \Gini\Config::get('database.'.$name);
            if (is_string($opt)) {
                // 是一个别名
                $db = static::db($opt);
            } else {
                if (!is_array($opt)) {
                    throw new Database\Exception('database "' . $name . '" was not configured correctly!');
                }

                $db = \Gini\IoC::construct('\Gini\Database', $opt['dsn'], $opt['username'], $opt['password'], $opt['options']);
            }

            static::$DB[$name] = $db;
        }

        return static::$DB[$name];
    }

    /**
     * Shutdown database by name
     * @param  string|null $name Name of the database configured in database.yml
     * @return void
     **/
    public static function shutdown($name=null)
    {
        $name = $name ?: 'default';
        if (!isset(static::$DB[$name])) {
            unset(static::$DB[$name]);
        }
    }

    /**
     * Shutdown all databases
     * @return void
     **/
    public static function reset()
    {
        static::$DB = [];
    }

    /**
     * Instantiate a database object with options
     * @param  string|null $name Name of the database configured in database.yml
     * @return void
     **/
    public function __construct($dsn, $username=null, $password=null, $options=null)
    {
        list($driver_name,) = explode(':', $dsn, 2);
        $driver_class = '\Gini\Database\\'.$driver_name;
        $this->_driver = \Gini\IoC::construct($driver_class, $dsn, $username, $password, $options);
        if (!$this->_driver instanceof Database\Driver) {
            throw new Database\Exception('unknown database driver: '.$driver_name);
        }
    }

    /**
     * Quote and concatenate multiple identities
     *   e.g. ident('db', 'table', 'field') => "db"."table"."field"
     *
     * @return string Concatenated SQL identities
     **/
    public function ident()
    {
        $args = func_get_args();
        $ident = array();
        foreach ($args as $arg) {
            $ident[] = $this->quoteIdent($arg);
        }

        return implode('.', $ident);
    }

    /**
     * Quote SQL identities with '"'.
     * If array was provided, convert it to "," concatenated string.
     *
     * @param  string|array $s String or array of strings to quote
     * @return string       Quoted identity string
     **/
    public function quoteIdent($s)
    {
        if (is_array($s)) {
            foreach ($s as &$i) {
                $i = $this->quoteIdent($i);
            }

            return implode(',', $s);
        }

        return $this->_driver->quoteIdent($s);
    }

    /**
     * Quote SQL value with "'" or not according variable type.
     * If array was provided, convert it to "," concatenated string.
     *
     * @param  mixed  $s Value or array of values to quote
     * @return string Quoted value
     **/
    public function quote($s)
    {
        if (is_array($s)) {
            foreach ($s as &$i) {
                $i=$this->quote($i);
            }

            return implode(',', $s);
        } elseif (is_null($s)) {
            return 'NULL';
        } elseif (is_bool($s)) {
            return $s ? 1 : 0;
        } elseif (is_int($s) || is_float($s)) {
            return $s;
        }

        return $this->_driver->quote($s);
    }

    /**
     * Retrieve a database connection attribute
     * @param  int    $attr One of the PDO::ATTR_* constants.
     * @return string
     **/
    public function attr($attr)
    {
        return $this->_driver->getAttribute($attr);
    }

    /**
     * Returns the ID of the last inserted row or sequence value
     * @param  string|null $name Name of the sequence object from which the ID should be returned.
     * @return string
     **/
    public function lastInsertId($name = null)
    {
        return $this->_driver->lastInsertId($name);
    }

    /**
     * Query SQL
     *
     * @param  string             $SQL    SQL with some placeholders for identities and parameters
     * @param  array|null         $idents Identities to replace
     * @param  array|null         $params Parameters to replace
     * @return Database\Statement
     **/
    public function query($SQL, $idents = null, $params = null)
    {
        // quote all identifiers
        if (is_array($idents)) {
            $quotedIdents = [];
            foreach ($idents as $k => $v) {
                $quotedIdents[$k] = $this->_driver->quoteIdent($v);
            }

            $SQL = strtr($SQL, $quotedIdents);
        }

        if (is_array($params)) {
            $this->_driver->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            \Gini\Logger::of('core')->debug("Database query prepare = {SQL}", ['SQL'=>preg_replace('/\s+/', ' ', $SQL)]);
            $st = $this->_driver->prepare($SQL);
            if (!$st) return false;

            \Gini\Logger::of('core')->debug("Database query execute = {params}", ['params'=>J($params)]);
            $success = $st->execute($params);
            if (!$success) return false;
            return new Database\Statement($st);
        }

        \Gini\Logger::of('core')->debug("Database query = {SQL}", [
            'SQL' => preg_replace('/\s+/', ' ', $SQL)
        ]);

        $st = $this->_driver->query($SQL);
        if (!$st) return false;
        return new Database\Statement($st);
    }

    /**
     * Run query and get the first field value of the first record.
     *
     * @return mixed
     **/
    public function value()
    {
        $args = func_get_args();
        $result = call_user_func_array([$this,'query'], $args);

        return $result ? $result->value() : null;
    }

    /**
     * Begin a transaction.
     *
     * @return self
     **/
    public function beginTransaction()
    {
        $this->_driver->beginTransaction();

        return $this;
    }

    /**
     * Commit the transaction.
     *
     * @return self
     **/
    public function commit()
    {
        $this->_driver->commit();

        return $this;
    }

    /**
     * Rollback the transaction.
     *
     * @return self
     **/
    public function rollback()
    {
        $this->_driver->rollBack();

        return $this;
    }

    /**
     * Create one table in the database.
     *
     * @param  string $table Table name
     * @return bool
     **/
    public function createTable($table)
    {
        return $this->_driver->createTable($table);
    }

    /**
     * Adjust table structure according schema.
     *
     * @param  string $table  Table name
     * @param  array  $schema Table schema
     * @return bool
     **/
    public function adjustTable($table, $schema)
    {
        return $this->_driver->adjustTable($table, $schema);
    }

    /**
     * Drop one specified table in the database.
     *
     * @param  string $table Specified table
     * @return bool
     **/
    public function dropTable($table)
    {
        $this->_driver->dropTable($table);
    }

    /**
     * Drop all tables in the database.
     *
     * @return bool
     **/
    public function emptyDatabase()
    {
        return $this->_driver->emptyDatabase();
    }

    // /**
    //  * Make a snapshot of the database and save it to provided path.
    //  * @param string $filename File path
    //  * @param array|null $tables Only snapshots specified tables
    //  * @return bool
    //  * @author Jia Huang
    //  **/
    // function snapshot($filename, $tables = null) {
    //
    //     if (is_string($tables)) $tables = array($tables);
    //     else $tables = (array) $tables;
    //
    //     return $this->_driver->snapshot($filename, $tables);
    // }
    //
    // /**
    //  * Restore a snapshot from files.
    //  *
    //  * @return bool
    //  * @author Jia Huang
    //  **/
    // function restore($filename, &$retore_filename=null, $tables=null) {
    //     $retore_filename = $filename.'.restore'.uniqid();
    //     if (!$this->snapshot($retore_filename)) return false;
    //
    //     if (is_string($tables)) $tables = array($tables);
    //     else $tables = (array) $tables;
    //
    //     if (count($tables) > 0) {
    //         call_user_func_array(array($this, 'dropTable'), $tables);
    //     }
    //     else {
    //         $this->emptyDatabase();
    //     }
    //
    //     return $this->_driver->restore($filename, $tables);
    // }

} // END class
