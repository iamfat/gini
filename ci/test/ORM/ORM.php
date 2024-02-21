<?php

namespace Gini\PHPUnit\ORM;

class ORM extends \Gini\PHPUnit\TestCase\CLI
{
    private $resultRows = [];
    private $queries = [];

    public function setUp(): void
    {
        parent::setUp();

        class_exists('\Gini\ORM');

        $db = $this->getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['query', 'quote', 'quoteIdent', 'lastInsertId', 'beginTransaction', 'commit', 'rollback'])
            ->disableOriginalConstructor()
            ->getMock();

        $db->expects($this->any())
            ->method('quoteIdent')
            ->will($this->returnCallback(function ($s) use ($db) {
                if (is_array($s)) {
                    foreach ($s as &$i) {
                        $i = $db->quoteIdent($i);
                    }
                    return implode(',', $s);
                } else if ($s instanceof \Gini\Database\SQL) {
                    return strval($s);
                }
                return '"' . addslashes($s) . '"';
            }));

        $db->expects($this->any())
            ->method('quote')
            ->will($this->returnCallback(function ($s) use ($db) {
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
                } else if ($s instanceof \Gini\Database\SQL) {
                    return strval($s);
                }
                return '\'' . addslashes($s) . '\'';
            }));

        $db->expects($this->any())
            ->method('lastInsertId')
            ->will($this->returnCallback(function () {
                return 1;
            }));

        $result = self::getMockBuilder('\Gini\Database\Statement')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['row', 'rows'])
            ->disableOriginalConstructor()
            ->getMock();

        $result->expects($this->any())
            ->method('row')
            ->will($this->returnCallback(function ($style = \PDO::FETCH_OBJ) {
                $r = current($this->resultRows);
                if ($r === false) return false;
                next($this->resultRows);
                return $style === \PDO::FETCH_OBJ ? (object)$r : $r;
            }));

        $result->expects($this->any())
            ->method('rows')
            ->will($this->returnCallback(function ($style = \PDO::FETCH_OBJ) {
                return $style === \PDO::FETCH_OBJ ? array_map(function ($v) {
                    return (object)$v;
                }, $this->resultRows) : $this->resultRows;
            }));

        $db->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($s, $s1, $s2) use ($result) {
                $this->queries[] = [$s, $s1, $s2];
                return $result;
            }));

        \Gini\IoC::bind('\Gini\ORM\UT\Sample', function ($criteria = null) use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'ownProperties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('ut/sample'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('ut_sample'));

            $o->expects($this->any())
                ->method('ownProperties')
                ->will($this->returnValue([
                    'id' => 'bigint,primary,serial',
                    '_extra' => 'array',
                    'object' => 'object',
                    'sample' => 'object:ut/sample',
                    'number' => 'int',
                    'boolean' => 'bool',
                    'datetime' => 'datetime',
                    'timestamp' => 'timestamp',
                    'text' => 'string',
                    'apple' => 'object:ut/sample,many',
                    'orange' => 'string:40,many',
                ]));

            unset($o->id);
            unset($o->_extra);
            if (isset($criteria)) {
                $criteria = $o->normalizeCriteria($o->criteria($criteria));
                $o->setData($criteria);
            }

            return $o;
        });
    }

    public function tearDown(): void
    {
        \Gini\IoC::clear('\Gini\ORM\UT\Sample');
        parent::tearDown();
    }

    public function testSaveObject()
    {
        $o1 = a('ut/sample');
        $o2 = a('ut/sample');

        $o2->id = 10;

        $o1->object = $o2;
        $o1->db()
            ->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($SQL) {
                self::assertEquals(
                    $SQL,
                    'INSERT INTO "ut_sample" SET "_extra"=\'{}\',"object_name"=\'ut/sample\',"object_id"=10,"sample_id"=NULL,"number"=0,"boolean"=0,"datetime"=\'0000-00-00 00:00:00\',"timestamp"=NOW(),"text"=\'\''
                );
            }));

        $o1->save();
    }

    public function testORMSchema()
    {
        $schema = a('ut/sample')->ormSchema();
        $fields = $schema['fields'];
        self::assertEquals($fields['id'], ['type' => 'bigint', 'serial' => true, 'default' => 0]);
        self::assertEquals($fields['_extra'], ['type' => 'text', 'default' => '{}']);
        self::assertEquals($fields['object_name'], ['type' => 'varchar(120)']);
        self::assertEquals($fields['object_id'], ['type' => 'bigint', 'null' => true]);
        self::assertEquals($fields['sample_id'], ['type' => 'bigint', 'null' => true]);
        self::assertEquals($fields['number'], ['type' => 'int', 'default' => 0]);
        self::assertEquals($fields['boolean'], ['type' => 'int', 'default' => 0]);
        self::assertEquals($fields['datetime'], ['type' => 'datetime', 'default' => '0000-00-00 00:00:00']);
        self::assertEquals($fields['timestamp'], ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP']);
        self::assertEquals($fields['text'], ['type' => 'varchar(255)', 'default' => '']);
    }

    public function testGet()
    {
        $o1 = a('ut/sample', 2);
        $o1->setData([
            'id' => 10,
            'object_name' => 'ut/sample',
            'object_id' => 2,
            'sample_id' => 4,
        ]);

        self::assertEquals($o1->id, 10);

        self::assertEquals($o1->object->id, 2);
        self::assertEquals($o1->sample->id, 4);

        self::assertEquals($o1->object_id, 2);
        self::assertEquals($o1->sample_id, 4);
    }

    public function testInjection()
    {
        $o1 = a('ut/sample', 2);
        $o1->inject([
            'properties' => [
                'inject_prop1' => 'string:*',
                'inject_prop2' => 'int'
            ],
            'indexes' => [
                'inject_prop1',
                'unique:inject_prop2'
            ],
            'relations' => [
                'sample' => 'update:cascade,delete:cascade'
            ]
        ]);

        $props = $o1->properties();
        self::assertEquals($props['inject_prop1'], 'string:*');
        self::assertEquals($props['inject_prop2'], 'int');
        self::assertEquals($o1->ormIndexes(), ['inject_prop1', 'unique:inject_prop2']);
        self::assertEquals($o1->ormRelations(), ['sample' => ['update' => 'cascade', 'delete' => 'cascade']]);
    }

    public function testORMAdditionalSchemas()
    {
        $o = a('ut/sample');
        self::assertEquals($o->ormAdditionalSchemas(), [
            '_ut_sample_apple' => [
                'fields' => [
                    'ut_sample_id' => [
                        'type' => 'bigint',
                        'null' => true,
                    ],
                    'apple_id' => [
                        'type' => 'bigint',
                        'null' => true
                    ]
                ],
                'indexes' => [
                    'PRIMARY' => ['type' => 'primary', 'fields' => ['ut_sample_id', 'apple_id']],
                ],
                'relations' => [
                    '_ut_sample_apple_ut_sample' => [
                        'delete' => 'cascade',
                        'update' => 'cascade',
                        'column' => 'ut_sample_id',
                        'ref_table' => 'ut_sample',
                        'ref_column' => 'id',
                    ],
                    '_ut_sample_apple_apple' => [
                        'delete' => 'cascade',
                        'update' => 'cascade',
                        'column' => 'apple_id',
                        'ref_table' => 'ut_sample',
                        'ref_column' => 'id',
                    ]
                ]
            ],
            '_ut_sample_orange' => [
                'fields' => [
                    'ut_sample_id' => [
                        'type' => 'bigint',
                        'null' => true,
                    ],
                    'orange' => [
                        'type' => 'varchar(40)',
                        'default' => '',
                    ]
                ],
                'indexes' => [
                    'PRIMARY' => ['type' => 'primary', 'fields' => ['ut_sample_id', 'orange']],
                ],
                'relations' => [
                    '_ut_sample_orange_ut_sample' => [
                        'delete' => 'cascade',
                        'update' => 'cascade',
                        'column' => 'ut_sample_id',
                        'ref_table' => 'ut_sample',
                        'ref_column' => 'id',
                    ]
                ]
            ]
        ]);
    }

    public function testGetMany()
    {
        $o1 = a('ut/sample', 1979);

        $o2 = a('ut/sample', 2);
        $o3 = a('ut/sample', 3);
        $o4 = a('ut/sample', 4);

        $o1->apple[$o2->id] = $o2;
        $o1->apple[$o3->id] = $o3;

        self::assertEquals($o1->apple->get('id'), [2 => 2, 3 => 3]);

        $o1->apple = [$o3, $o4];
        self::assertEquals($o1->apple->get('id'), [3 => 3, 4 => 4]);

        $o1->apple = [$o2, $o3, $o4];
        $this->resultRows = [
            ['oid' => 1],
            ['oid' => 2],
        ];
        $o1->save();

        $db = $o1->db();

        $SQLs = [];
        foreach ($this->queries as list($SQL, $idents, $params)) {
            if (is_array($idents)) {
                $conversions = [];
                foreach ($idents as $k => $v) {
                    $conversions[$k] = $db->quoteIdent($v);
                }
                $SQL = strtr($SQL, $conversions);
            }
            if (is_array($params)) {
                $conversions = [];
                foreach ($params as $k => $v) {
                    $conversions[$k] = $db->quote($v);
                }
                $SQL = strtr($SQL, $conversions);
            }
            $SQLs[] = $SQL;
        }

        self::assertEquals($SQLs[2], 'SELECT "apple_id" AS oid FROM "_ut_sample_apple" WHERE "ut_sample_id"=1979 FOR UPDATE');
        self::assertEquals($SQLs[3], 'DELETE FROM "_ut_sample_apple" WHERE "ut_sample_id"=1979 AND "apple_id" IN (1)');
        self::assertEquals($SQLs[4], 'INSERT INTO "_ut_sample_apple" ("ut_sample_id", "apple_id") VALUES (1979,3),(1979,4)');
    }
}
