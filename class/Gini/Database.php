<?php

/**
 * Database Abstract Layer.
 *
 * @author Jia Huang
 *
 * @version $Id$
 *
 * @copyright Genee, 2014-01-27
 **/

/**
 * Define DocBlock.
 **/

namespace Gini {

    use \Gini\Database\SQL;

    /**
     * Database Abstract Layer.
     *
     * @author Jia Huang
     **/
    class Database
    {
        /**
         * Loaded databases;.
         *
         * @var array
         **/
        public static $DB = array();

        private $_driver;

        /**
         * Get a database object by name.
         *
         * @param string|null $name Name of the database configured in database.yml
         *
         * @return \Gini\Database
         * @throws \Gini\Database\Exception
         **/
        public static function db($name = null)
        {
            $name = $name ?: 'default';
            if (!isset(self::$DB[$name])) {
                $opt = \Gini\Config::get('database.' . $name);
                if (is_string($opt)) {
                    // 是一个别名
                    $db = static::db($opt);
                } else {
                    if (!is_array($opt)) {
                        throw new Database\Exception('database "' . $name . '" was not configured correctly!');
                    }

                    $db = \Gini\IoC::construct('\Gini\Database', $opt['dsn'] ?? null, $opt['username'] ?? null, $opt['password'] ?? null, $opt['options'] ?? null);
                }

                static::$DB[$name] = $db;
            }

            return static::$DB[$name];
        }

        /**
         * Shutdown database by name.
         *
         * @param string|null $name Name of the database configured in database.yml
         **/
        public static function shutdown($name = null)
        {
            $name = $name ?: 'default';
            if (isset(static::$DB[$name])) {
                unset(static::$DB[$name]);
            }
        }

        /**
         * Shutdown all databases.
         **/
        public static function reset()
        {
            static::$DB = [];
        }

        /**
         * Instantiate a database object with options.
         *
         * @param string|null $name Name of the database configured in database.yml
         **/
        public function __construct($dsn, $username = null, $password = null, $options = null)
        {
            list($driver_name) = explode(':', $dsn, 2);
            $driver_class = '\Gini\Database\\' . $driver_name ?: 'Unknown';
            $this->_driver = \Gini\IoC::construct($driver_class, $dsn, $username, $password, $options);
            // if (!$this->_driver instanceof Database\Driver) {
            //     throw new Database\Exception('unknown database driver: ' . $driver_name);
            // }
        }

        /**
         * Quote and concatenate multiple identities
         *   e.g. ident('db', 'table', 'field') => "db"."table"."field".
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
         * @param string|array $s String or array of strings to quote
         *
         * @return string Quoted identity string
         **/
        public function quoteIdent($s)
        {
            if (is_array($s)) {
                foreach ($s as &$i) {
                    $i = $this->quoteIdent($i);
                }
                return implode(',', $s);
            } elseif ($s instanceof SQL) {
                return strval($s);
            }
            return $this->_driver->quoteIdent($s);
        }

        /**
         * Quote SQL value with "'" or not according variable type.
         * If array was provided, convert it to "," concatenated string.
         *
         * @param mixed $s Value or array of values to quote
         *
         * @return string Quoted value
         **/
        public function quote($s)
        {
            if (is_array($s)) {
                foreach ($s as &$i) {
                    $i = $this->quote($i);
                }
                return implode(',', $s);
            } elseif (is_null($s)) {
                return 'NULL';
            } elseif (is_bool($s)) {
                return $s ? 1 : 0;
            } elseif (is_int($s) || is_float($s)) {
                return $s;
            } elseif ($s instanceof SQL) {
                return strval($s);
            }
            return $this->_driver->quote($s);
        }

        /**
         * Retrieve a database connection attribute.
         *
         * @param int $attr One of the PDO::ATTR_* constants.
         *
         * @return string
         **/
        public function attr($attr)
        {
            return $this->_driver->getAttribute($attr);
        }

        /**
         * Returns the ID of the last inserted row or sequence value.
         *
         * @param string|null $name Name of the sequence object from which the ID should be returned.
         *
         * @return string
         **/
        public function lastInsertId($name = null)
        {
            return $this->_driver->lastInsertId($name);
        }

        public function prepareSQL($SQL, $idents = null, $params = null)
        {
            // quote all identifiers
            if (is_array($idents)) {
                $conversions = [];
                foreach ($idents as $k => $v) {
                    $conversions[$k] = $this->quoteIdent($v);
                }
                $SQL = strtr($SQL, $conversions);
            }

            $filtered_params = [];
            if (is_array($params)) {
                $conversions = [];
                foreach ($params as $k => $v) {
                    if (is_array($v) || $v instanceof SQL) {
                        $conversions[$k] = $this->quote($v);
                    } else {
                        $filtered_params[$k] = $v;
                    }
                }
                $SQL = strtr($SQL, $conversions);
            }

            return [$SQL, $filtered_params];
        }

        /**
         * Query SQL.
         *
         * @param string     $SQL    SQL with some placeholders for identities and parameters
         * @param array|null $idents Identities to replace
         * @param array|null $params Parameters to replace
         *
         * @return Database\Statement
         **/
        public function query($SQL, $idents = null, $params = null)
        {
            list($SQL, $params) = $this->prepareSQL($SQL, $idents, $params);

            if (is_array($params) && count($params) > 0) {
                $this->_driver->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
                \Gini\Logger::of('core')->debug('Database query prepare = {SQL}', ['SQL' => preg_replace('/\s+/', ' ', $SQL)]);
                $st = $this->_driver->prepare($SQL);
                if (!$st) {
                    return false;
                }

                \Gini\Logger::of('core')->debug('Database query execute = {params}', ['params' => J($params)]);
                $success = $st->execute($params);
                if (!$success) {
                    return false;
                }

                return new Database\Statement($st);
            }

            \Gini\Logger::of('core')->debug('Database query = {SQL}', [
                'SQL' => preg_replace('/\s+/', ' ', $SQL),
            ]);

            $st = $this->_driver->query($SQL);
            if (!$st) {
                return false;
            }

            return new Database\Statement($st);
        }

        /**
         * executes an SQL statement in a single function call
         * returning the number of rows affected by the statement.
         *
         * @param string $SQL
         *
         * @return int Number of rows affected
         *
         * @author Jia Huang
         */
        public function exec($SQL)
        {
            \Gini\Logger::of('core')->debug('Database exec = {SQL}', [
                'SQL' => preg_replace('/\s+/', ' ', $SQL),
            ]);

            return $this->_driver->exec($SQL);
        }

        /**
         * Run query and get the first field value of the first record.
         *
         * @return mixed
         **/
        public function value()
        {
            $args = func_get_args();
            $result = call_user_func_array([$this, 'query'], $args);

            return $result ? $result->value() : null;
        }

        private $_transactionLevel = 0;
        private $_transactionRollback = false;
        /**
         * Begin a transaction.
         *
         * @return self
         **/
        public function beginTransaction()
        {
            // check if you are at the top of the transaction;
            if ($this->_transactionLevel == 0) {
                $this->_transactionRollback = false;
                $this->_driver->beginTransaction();
            }

            ++$this->_transactionLevel;

            return $this;
        }

        /**
         * Commit the transaction.
         *
         * @return self
         **/
        public function commit()
        {
            if ($this->_transactionLevel > 0) {
                --$this->_transactionLevel;
                if ($this->_transactionLevel == 0) {
                    if ($this->_transactionRollback) {
                        $this->_driver->rollback();
                    } else {
                        $this->_driver->commit();
                    }
                }
            }

            return $this;
        }

        /**
         * Rollback the transaction.
         *
         * @return self
         **/
        public function rollback()
        {
            if ($this->_transactionLevel > 0) {
                --$this->_transactionLevel;
                if ($this->_transactionLevel == 0) {
                    $this->_driver->rollBack();
                } else {
                    $this->_transactionRollback = true;
                }
            }

            return $this;
        }

        /**
         * Create one table in the database.
         *
         * @param string $table Table name
         *
         * @return bool
         **/
        public function createTable($table)
        {
            return $this->_driver->createTable($table);
        }

        public const ADJFLAG_REMOVE_NONEXISTENT = 0x01;

        /**
         * Adjust table structure according schema.
         *
         * @param string $table  Table name
         * @param array  $schema Table schema
         *
         * @return bool
         **/
        public function adjustTable($table, $schema, $flag = 0)
        {
            return $this->_driver->adjustTable($table, $schema, $flag);
        }

        /**
         * Drop one specified table in the database.
         *
         * @param string $table Specified table
         *
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

        /**
         * @brief diagnose
         *
         * @return
         */
        public function diagnose()
        {
            return $this->_driver->diagnose();
        }

        // allow transparent calling
        public function __call($method, $params)
        {
            if (!$this->_driver) {
                throw new \BadMethodCallException('Method ' . $method . ' is not callable');
            }
            return call_user_func_array([$this->_driver, $method], $params);
        }
    } // END class
}

namespace {
    if (function_exists('SQL')) {
        die('SQL() was declared by other libraries, which may cause problems!');
    } else {
        /**
         * @param $SQL
         *
         * @return \Gini\Database\SQL
         */
        function SQL($SQL)
        {
            return new \Gini\Database\SQL($SQL);
        }
    }
}
