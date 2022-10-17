<?php

namespace Gini\PHPUnit\ORM;

class Database extends \Gini\PHPUnit\TestCase\CLI
{
    public function testPrepareSQL()
    {
        $db = new \Gini\Database('unknown:');

        list($SQL, $params) = $db->prepareSQL('SELECT :field FROM :table WHERE :field IN (:values) AND :expression AND foo=:bar AND t=:now', [
            ':table' => 'table1',
            ':field' => 'field1',
            ':expression' => SQL('hello=1')
        ], [
            ':values' => [1, 2, 3],
            ':bar' => 'bar',
            ':now' => SQL('NOW()')
        ]);

        self::assertEquals('SELECT "field1" FROM "table1" WHERE "field1" IN (1,2,3) AND hello=1 AND foo=:bar AND t=NOW()', $SQL);
        self::assertEquals([':bar' => 'bar'], $params);
    }
}
