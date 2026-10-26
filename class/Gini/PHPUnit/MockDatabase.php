<?php

namespace Gini\PHPUnit;

/**
 * Shared construction of \Gini\Database test doubles:
 * disableOriginalConstructor + setMethods + standard quote/quoteIdent/ident
 * callbacks, query-result attachment and IoC bind/clear bookkeeping.
 *
 * Mocks of other classes (e.g. \Gini\Database\Statement, \Gini\HTTP) stay
 * in the individual test files.
 */
trait MockDatabase
{
    private $mockIoCKeys = [];

    /**
     * Build a \Gini\Database mock with the standard quote()/quoteIdent()
     * callbacks (and ident() when it is part of $methods).
     *
     * @param array $methods stubbed method set, verbatim per call site
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function createDatabaseMock(array $methods = ['query', 'quote', 'quoteIdent', 'ident'], $quoteSQLObjects = false)
    {
        $db = $this->getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();

        $db->expects($this->any())
            ->method('quoteIdent')
            ->will($this->returnCallback(function ($s) use ($db, $quoteSQLObjects) {
                if (is_array($s)) {
                    foreach ($s as &$i) {
                        $i = $db->quoteIdent($i);
                    }

                    return implode(',', $s);
                } elseif ($quoteSQLObjects && $s instanceof \Gini\Database\SQL) {
                    return strval($s);
                }

                return '"' . addslashes($s) . '"';
            }));

        $db->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function ($s) use ($db, $quoteSQLObjects) {
                if (is_array($s)) {
                    foreach ($s as &$i) {
                        $i = $db->quote($i);
                    }

                    return implode(',', $s);
                } elseif (is_null($s)) {
                    return 'NULL';
                } elseif (is_bool($s)) {
                    return $s ? 1 : 0;
                } elseif (is_int($s) || is_float($s)) {
                    return $s;
                } elseif ($quoteSQLObjects && $s instanceof \Gini\Database\SQL) {
                    return strval($s);
                }

                return '\'' . addslashes($s) . '\'';
            }));

        if (in_array('ident', $methods)) {
            $db->expects($this->any())
                ->method('ident')
                ->will($this->returnCallback(
                    function ($s) use ($db) {
                        $args = func_get_args();
                        $ident = [];
                        foreach ($args as $arg) {
                            $ident[] = $db->quoteIdent($arg);
                        }

                        return implode('.', $ident);
                    }
                ));
        }

        return $db;
    }

    /**
     * Attach the query() stub: every query returns $result, optionally
     * passing (SQL, idents, others) to $recorder for assertions.
     *
     * @param object        $result   statement mock returned for every query
     * @param callable|null $recorder function ($SQL, $idents, $others)
     *
     * @return void
     */
    protected function attachQueryHandler($db, $result, ?callable $recorder = null)
    {
        $db->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($SQL, $idents, $others) use ($result, $recorder) {
                if ($recorder) {
                    $recorder($SQL, $idents, $others);
                }

                return $result;
            }));
    }

    /**
     * Bind an IoC key and remember it for clearMockIoC().
     */
    protected function bindIoC($key, callable $factory)
    {
        \Gini\IoC::bind($key, $factory);
        $this->mockIoCKeys[] = $key;
    }

    /**
     * Clear every IoC binding registered via bindIoC() since the last clear.
     */
    protected function clearMockIoC()
    {
        foreach ($this->mockIoCKeys as $key) {
            \Gini\IoC::clear($key);
        }
        $this->mockIoCKeys = [];
    }
}
