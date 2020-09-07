<?php

namespace Gini\PHPUnit\ORM {

    class ORM extends \Gini\PHPUnit\TestCase\CLI
    {
        public function setUp()
        {
            parent::setUp();

            class_exists('\Gini\Those');

            $db = $this->getMockBuilder('\Gini\Database')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['query', 'quote', 'quoteIdent', 'beginTransaction', 'commit', 'lastInsertId'])
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
                    }

                    return '\'' . addslashes($s) . '\'';
                }));
            $db->expects($this->any())
                ->method('beginTransaction')
                ->will($this->returnCallback(function () {
                    return true;
                }));
            $db->expects($this->any())
                ->method('commit')
                ->will($this->returnCallback(function () {
                    return true;
                }));
            $db->expects($this->any())
                ->method('lastInsertId')
                ->will($this->returnCallback(function () {
                    return 5;
                }));

            \Gini\IoC::bind('\Gini\ORM\UTSample', function ($criteria = null) use ($db) {
                $o = $this->getMockBuilder('\Gini\ORM\Object')
                    ->setMockClassName('MOBJ_' . uniqid())
                    ->setMethods(['db', 'ownProperties', 'name', 'tableName'])
                    ->disableOriginalConstructor()
                    ->getMock();

                $o->expects($this->any())
                    ->method('db')
                    ->will($this->returnValue($db));

                $o->expects($this->any())
                    ->method('name')
                    ->will($this->returnValue('utsample'));

                $o->expects($this->any())
                    ->method('tableName')
                    ->will($this->returnValue('utsample'));

                $o->expects($this->any())
                    ->method('ownProperties')
                    ->will($this->returnValue([
                        'id' => 'bigint,primary,serial',
                        '_extra' => 'array',
                        'object' => 'object',
                        'sample' => 'object:utsample',
                        'number' => 'int',
                        'boolean' => 'bool',
                        'datetime' => 'datetime',
                        'timestamp' => 'timestamp',
                        'text' => 'string',
                        'apple' => 'object:utsample,many',
                        'orange' => 'string:40,many',
                        'banana' => 'object,many',
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

        public function tearDown()
        {
            \Gini\IoC::clear('\Gini\ORM\UTSample');
            parent::tearDown();
        }

        public function testSaveObject()
        {
            $o1 = a('utsample');
            $o2 = a('utsample');

            $o2->id = 10;

            $o1->object = $o2;
            $o1->db()
                ->expects($this->any())
                ->method('query')
                ->will($this->returnCallback(function ($SQL) {
                    $this->assertEquals(
                        $SQL,
                        'INSERT INTO "utsample" SET "_extra"=\'{}\',"object_name"=\'utsample\',"object_id"=10,"sample_id"=NULL,"number"=0,"boolean"=0,"datetime"=\'0000-00-00 00:00:00\',"timestamp"=NOW(),"text"=\'\''
                    );
                    return true;
                }));

            $o1->save();
        }

        public function testSaveObjectMany()
        {
            $o2 = a('utsample');
            $o2->id = 10;
            $o3 = a('utsample');
            $o3->id = 30;
            $o4 = a('utsample');
            $o4->apple = [$o2, $o3];
            $o4->orange = ['a', 'b', 'c'];
            $o4->banana = [$o2, $o3];
            $sql_list = [];

            $sql_results = [
                'select apple_id as id from "_utsample_apple" where utsample_id = 5' => [],
                'select orange as id from "_utsample_orange" where utsample_id = 5' => [],
                'select banana_id as id,banana_name as \'name\' from "_utsample_banana" where utsample_id = 5' => []
            ];
            $sql_now = null;

            $query = self::getMockBuilder('\Gini\Database\Statement')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['rows'])
                ->disableOriginalConstructor()
                ->getMock();
            $query->expects($this->any())
                ->method('rows')
                ->will($this->returnCallback(function () use ($sql_results, &$sql_now) {
                    if (isset($sql_results[$sql_now])) {
                        return $sql_results[$sql_now];
                    } else {
                        return null;
                    }
                }));

            $o4->db()
                ->expects($this->any())
                ->method('query')
                ->will($this->returnCallback(function ($SQL) use (&$sql_list, $query, $sql_results, &$sql_now) {
                    if (isset($sql_results[$SQL])) {
                        $sql_now = $SQL;
                        return $query;
                    }
                    $sql_list[] = $SQL;
                    return true;
                }));
            $o4->save();
            $this->assertEquals($sql_list, [
                'INSERT INTO "utsample" SET "_extra"=\'{}\',"object_name"=NULL,"object_id"=NULL,"sample_id"=NULL,"number"=0,"boolean"=0,"datetime"=\'0000-00-00 00:00:00\',"timestamp"=NOW(),"text"=\'\'',
                'insert into "_utsample_apple" (utsample_id,apple_id) values (5,10);',
                'insert into "_utsample_apple" (utsample_id,apple_id) values (5,30);',
                'insert into "_utsample_orange" (utsample_id,orange) values (5,\'a\');',
                'insert into "_utsample_orange" (utsample_id,orange) values (5,\'b\');',
                'insert into "_utsample_orange" (utsample_id,orange) values (5,\'c\');',
                'insert into "_utsample_banana" (utsample_id,banana_name,banana_id) values (5,\'utsample\',10);',
                'insert into "_utsample_banana" (utsample_id,banana_name,banana_id) values (5,\'utsample\',30);'
            ]);
        }

        public function testUpdateObjectMany(){
            $o2 = a('utsample');
            $o2->id = 10;
            $o3 = a('utsample');
            $o3->id = 30;
            $o4 = a('utsample',9);
            $o4->apple = [$o2, $o3];
            $o4->orange = ['a', 'b', 'c'];
            $o4->banana = [$o2, $o3];
            $sql_list = [];

            $sql_results = [
                'SELECT "id" FROM "utsample" WHERE "id"=?' => [
                    json_decode('{"id":9}'),
                ],
                'select apple_id as id from "_utsample_apple" where utsample_id = 9' => [
                    json_decode('{"id":1}'),
                    json_decode('{"id":10}'),
                ],
                'select orange as id from "_utsample_orange" where utsample_id = 9' => [
                    json_decode('{"id":"a"}'),
                    json_decode('{"id":"d"}'),
                ],
                'select banana_id as id,banana_name as \'name\' from "_utsample_banana" where utsample_id = 9' => [
                    json_decode('{"id":6,"name":"utsample"}'),
                    json_decode('{"id":10,"name":"utsample"}'),
                ]
            ];
            $sql_now = null;

            $query = self::getMockBuilder('\Gini\Database\Statement')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['rows'])
                ->disableOriginalConstructor()
                ->getMock();
            $query->expects($this->any())
                ->method('rows')
                ->will($this->returnCallback(function () use ($sql_results, &$sql_now) {
                    if (isset($sql_results[$sql_now])) {
                        return $sql_results[$sql_now];
                    } else {
                        return null;
                    }
                }));

            $o4->db()
                ->expects($this->any())
                ->method('query')
                ->will($this->returnCallback(function ($SQL) use (&$sql_list, $query, $sql_results, &$sql_now) {
                    if (isset($sql_results[$SQL])) {
                        $sql_now = $SQL;
                        return $query;
                    }
                    $sql_list[] = $SQL;
                    return true;
                }));
            $o4->save();
            $this->assertEquals($sql_list, [
                'UPDATE "utsample" SET "_extra"=\'{}\',"object_name"=NULL,"object_id"=NULL,"sample_id"=NULL,"number"=0,"boolean"=0,"datetime"=\'0000-00-00 00:00:00\',"timestamp"=NOW(),"text"=\'\' WHERE "id"=9',
                'delete from "_utsample_apple" where utsample_id=9 and apple_id=1;',
                'insert into "_utsample_apple" (utsample_id,apple_id) values (9,30);',
                'delete from "_utsample_orange" where utsample_id=9 and orange=\'d\';',
                'insert into "_utsample_orange" (utsample_id,orange) values (9,\'b\');',
                'insert into "_utsample_orange" (utsample_id,orange) values (9,\'c\');',
                'delete from "_utsample_banana" where utsample_id=9 and banana_name=\'utsample\' and banana_id=6;',
                'insert into "_utsample_banana" (utsample_id,banana_name,banana_id) values (9,\'utsample\',30);'
            ]);
        }

        public function testORMSchema()
        {
            $schema = a('utsample')->ormSchema();
            $fields = $schema['fields'];
            $this->assertEquals($fields['id'], ['type' => 'bigint', 'serial' => true, 'default' => 0]);
            $this->assertEquals($fields['_extra'], ['type' => 'text', 'default' => '{}']);
            $this->assertEquals($fields['object_name'], ['type' => 'varchar(120)']);
            $this->assertEquals($fields['object_id'], ['type' => 'bigint', 'null' => true]);
            $this->assertEquals($fields['sample_id'], ['type' => 'bigint', 'null' => true]);
            $this->assertEquals($fields['number'], ['type' => 'int', 'default' => 0]);
            $this->assertEquals($fields['boolean'], ['type' => 'int', 'default' => 0]);
            $this->assertEquals($fields['datetime'], ['type' => 'datetime', 'default' => '0000-00-00 00:00:00']);
            $this->assertEquals($fields['timestamp'], ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP']);
            $this->assertEquals($fields['text'], ['type' => 'varchar(255)', 'default' => '']);
        }

        public function testGet()
        {
            $o1 = a('utsample', 2);
            $o1->setData([
                'id' => 10,
                'object_name' => 'utsample',
                'object_id' => 2,
                'sample_id' => 4,
            ]);

            $this->assertEquals($o1->id, 10);

            $this->assertEquals($o1->object->id, 2);
            $this->assertEquals($o1->sample->id, 4);

            $this->assertEquals($o1->object_id, 2);
            $this->assertEquals($o1->sample_id, 4);
        }

        public function testInjection()
        {
            $o1 = a('utsample', 2);
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
            $this->assertEquals($props['inject_prop1'], 'string:*');
            $this->assertEquals($props['inject_prop2'], 'int');
            $this->assertEquals($o1->ormIndexes(), ['inject_prop1', 'unique:inject_prop2']);
            $this->assertEquals($o1->ormRelations(), ['sample' => ['update' => 'cascade', 'delete' => 'cascade']]);
        }

        public function testORMAdditionalSchemas()
        {
            $o = a('utsample');
            $this->assertEquals($o->ormAdditionalSchemas(), [
                '_utsample_apple' => [
                    'fields' => [
                        'utsample_id' => [
                            'type' => 'bigint',
                            'null' => true,
                        ],
                        'apple_id' => [
                            'type' => 'bigint',
                            'null' => true
                        ]
                    ],
                    'indexes' => [
                        'PRIMARY' => ['type' => 'primary', fields => ['utsample_id', 'apple_id']],
                    ],
                    'relations' => [
                        '_utsample_apple_utsample' => [
                            'delete' => 'cascade',
                            'update' => 'cascade',
                            'column' => 'utsample_id',
                            'ref_table' => 'utsample',
                            'ref_column' => 'id',
                        ],
                        '_utsample_apple_apple' => [
                            'delete' => 'cascade',
                            'update' => 'cascade',
                            'column' => 'apple_id',
                            'ref_table' => 'utsample',
                            'ref_column' => 'id',
                        ]
                    ]
                ],
                '_utsample_orange' => [
                    'fields' => [
                        'utsample_id' => [
                            'type' => 'bigint',
                            'null' => true,
                        ],
                        'orange' => [
                            'type' => 'varchar(40)',
                            'default' => '',
                        ]
                    ],
                    'indexes' => [
                        'PRIMARY' => ['type' => 'primary', fields => ['utsample_id', 'orange']],
                    ],
                    'relations' => [
                        '_utsample_orange_utsample' => [
                            'delete' => 'cascade',
                            'update' => 'cascade',
                            'column' => 'utsample_id',
                            'ref_table' => 'utsample',
                            'ref_column' => 'id',
                        ]
                    ]
                ],
                '_utsample_banana' => [
                    'fields' => [
                        'utsample_id' => [
                            'type' => 'bigint',
                            'null' => true,
                        ],
                        'banana_id' => [
                            'type' => 'bigint',
                            'null' => true,
                        ],
                        'banana_name' => [
                            'type' => 'varchar(120)',
                        ]
                    ],
                    'indexes' => [
                        'PRIMARY' => ['type' => 'primary', 'fields' => ['utsample_id', 'banana_id', 'banana_name']],
                    ],
                    'relations' => [
                        '_utsample_banana_utsample' => [
                            'delete' => 'cascade',
                            'update' => 'cascade',
                            'column' => 'utsample_id',
                            'ref_table' => 'utsample',
                            'ref_column' => 'id',
                        ]
                    ]
                ]
            ]);
        }
    }

}
