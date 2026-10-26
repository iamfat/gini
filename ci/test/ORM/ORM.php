<?php

namespace Gini\PHPUnit\ORM;

class ORM extends \Gini\PHPUnit\TestCase\CLI
{
    use \Gini\PHPUnit\MockDatabase;

    private $resultRows = [];
    private $queries = [];
    private $db;
    public function setUp(): void
    {
        parent::setUp();

        class_exists('\Gini\ORM');

        $db = $this->createDatabaseMock(['query', 'quote', 'quoteIdent', 'lastInsertId', 'beginTransaction', 'commit', 'rollback'], true);
        $this->db = $db;
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

        $this->attachQueryHandler($db, $result, function ($s, $s1, $s2) {
            $this->queries[] = [$s, $s1, $s2];
        });

        $this->sampleProperties = $sampleProperties = [
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
        ];

        \Gini\IoC::bind('\Gini\ORM\UT\Sample', function ($criteria = null) use ($db, $sampleProperties) {
            return $this->makeOrmMock($db, 'ut/sample', 'ut_sample', $sampleProperties, $criteria);
        });

        // 多态 many(object,many, 无固定 oname) + 自有命名 object many + 标量 many
        \Gini\IoC::bind('\Gini\ORM\UT\Poly', function ($criteria = null) use ($db) {
            return $this->makeOrmMock($db, 'ut/poly', 'ut_poly', [
                'id' => 'bigint,primary,serial',
                'thing' => 'object,many',
                'tag' => 'string:20,many',
                'friend' => 'object:ut/poly,many',
            ], $criteria);
        });
    }

    /**
     * Build a \Gini\ORM\Base mock wired to the given database mock.
     */
    private function makeOrmMock($db, $name, $tableName, array $properties, $criteria = null, array $methods = [], bool $hydrate = true)
    {
        $o = $this->getMockBuilder('\Gini\ORM\Base')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(array_merge(['db', 'ownProperties', 'name', 'tableName'], $methods))
            ->disableOriginalConstructor()
            ->getMock();

        $o->expects($this->any())
            ->method('db')
            ->will($this->returnValue($db));

        $o->expects($this->any())
            ->method('name')
            ->will($this->returnValue($name));

        $o->expects($this->any())
            ->method('tableName')
            ->will($this->returnValue($tableName));

        $o->expects($this->any())
            ->method('ownProperties')
            ->will($this->returnValue($properties));

        unset($o->id);
        unset($o->_extra);
        if (isset($criteria)) {
            $normalized = $o->normalizeCriteria($o->criteria($criteria));
            if ($hydrate) {
                $o->setData($normalized);
            }
        }

        return $o;
    }

    private function sampleRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 5,
            '_extra' => '{}',
            'object_name' => null,
            'object_id' => null,
            'sample_id' => null,
            'number' => 0,
            'boolean' => 0,
            'datetime' => '0000-00-00 00:00:00',
            'timestamp' => null,
            'text' => '',
        ], $overrides);
    }

    private function renderSQL(array $entry, $db): string
    {
        list($SQL, $idents, $params) = $entry;
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

        return $SQL;
    }

    private function renderedQueries($db): array
    {
        return array_map(function ($entry) use ($db) {
            return $this->renderSQL($entry, $db);
        }, $this->queries);
    }

    private function findSQL($db, string $needle): string
    {
        foreach ($this->renderedQueries($db) as $SQL) {
            if (strpos($SQL, $needle) !== false) {
                return $SQL;
            }
        }
        self::fail("SQL containing '$needle' was never executed");
    }

    public function tearDown(): void
    {
        \Gini\IoC::clear('\Gini\ORM\UT\Sample');
        \Gini\IoC::clear('\Gini\ORM\UT\Poly');
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

    // ========== 3.1 delete / forceDelete ==========

    public function testForceDeleteEmitsParameterizedDeleteSQL(): void
    {
        $o = a('ut/sample');
        $o->setData($this->sampleRow(['id' => 77]));

        self::assertTrue($o->forceDelete());
        $SQL = $this->findSQL($this->db, 'DELETE FROM "ut_sample"');
        self::assertSame('DELETE FROM "ut_sample" WHERE "id"=77', $SQL);
    }

    public function testDeleteWithoutIdIsNoOp(): void
    {
        $o = a('ut/sample');
        $count = count($this->queries);
        self::assertTrue($o->delete());
        self::assertTrue($o->forceDelete());
        self::assertCount($count, $this->queries);
    }

    public function testDeleteAlwaysUsesForceDeleteHookCheckIsDeadBranch(): void
    {
        // 代码现状怪癖（实测 PHP 7.4/8.5 一致）：delete() 写的是
        // is_callable($this, '_delete')，第二参数实为 syntax_only 布尔位，
        // 对对象恒返回 false（对象无 __invoke），故即便定义了 _delete()
        // 也永远走 forceDelete()。本用例钉扎该真实行为。
        $this->resultRows = [];
        $o = $this->makeOrmMock($this->db, 'ut/sample', 'ut_sample', [
            'id' => 'bigint,primary,serial',
        ], ['id' => 9], ['_delete']);
        $o->expects($this->never())->method('_delete');

        self::assertTrue($o->delete());
        self::assertSame(
            'DELETE FROM "ut_sample" WHERE "id"=9',
            $this->findSQL($this->db, 'DELETE FROM "ut_sample"')
        );
    }

    // ========== 3.1 fetch ==========

    public function testFetchLoadsRowByCriteria(): void
    {
        $this->resultRows = [[
            'id' => 5,
            '_extra' => '{}',
            'object_name' => null,
            'object_id' => null,
            'sample_id' => null,
            'number' => 42,
            'boolean' => 1,
            'datetime' => '2026-01-02 03:04:05',
            'timestamp' => '2026-01-02 03:04:05',
            'text' => 'loaded',
        ]];

        $o = $this->makeOrmMock($this->db, 'ut/sample', 'ut_sample', $this->sampleProperties, ['id' => 5], [], false);
        $o->fetch();
        self::assertSame('loaded', $o->text);
        self::assertSame(42, (int) $o->number);

        $SQL = $this->findSQL($this->db, 'FROM "ut_sample" WHERE');
        self::assertStringStartsWith('SELECT ', $SQL);
        self::assertStringContainsString(' FROM "ut_sample" WHERE "id"=5 LIMIT 1', $SQL);
    }

    public function testFetchWithoutCriteriaIssuesNoSQL(): void
    {
        $o = a('ut/sample');
        $count = count($this->queries);
        $o->fetch(true);
        self::assertCount($count, $this->queries);
    }

    public function testFetchForUpdateAppendsLockClauseOnce(): void
    {
        $o = $this->makeOrmMock($this->db, 'ut/sample', 'ut_sample', $this->sampleProperties, ['id' => 5], [], false);
        $o->forUpdate();
        $o->fetch();
        $SQL = $this->findSQL($this->db, 'FOR UPDATE');
        self::assertStringEndsWith(' LIMIT 1 FOR UPDATE', $SQL);

        // FOR UPDATE 位在一次 fetch 后被消耗；强制重取不再追加
        $count = count($this->queries);
        $o->fetch(true);
        foreach (array_slice($this->queries, $count) as $entry) {
            self::assertStringNotContainsString('FOR UPDATE', $this->renderSQL($entry, $this->db));
        }
        self::assertGreaterThan($count, count($this->queries));
    }

    // ========== 3.1 casting ==========

    public function testSetDataCastsWhenAutocastEnabled(): void
    {
        $o = a('ut/sample');
        $rp = new \ReflectionProperty(\Gini\ORM::class, 'autocast');
        $rp->setAccessible(true);
        $rp->setValue($o, true);

        $o->setData($this->sampleRow([
            'id' => '5',
            'number' => '42',
            'boolean' => '1',
        ]));

        self::assertSame(5, $o->id);
        self::assertSame(42, $o->number);
        self::assertTrue($o->boolean);
    }

    public function testSetDataKeepsRawValuesWhenAutocastDisabled(): void
    {
        $o = a('ut/sample');
        $o->setData($this->sampleRow([
            'id' => '5',
            'number' => '42',
            'boolean' => '1',
        ]));
        self::assertSame('5', $o->id);
        self::assertSame('42', $o->number);
        self::assertSame('1', $o->boolean);
    }

    // ========== 3.1 save() transaction ==========

    public function testSaveInsertBeginsAndCommitsTransaction(): void
    {
        $this->db->expects($this->once())->method('beginTransaction');
        $this->db->expects($this->once())->method('commit');

        $o = a('ut/sample');
        $o->text = 'hi';
        self::assertTrue($o->save());

        $SQL = $this->findSQL($this->db, 'INSERT INTO "ut_sample"');
        self::assertStringContainsString('"text"=\'hi\'', $SQL);
    }

    public function testSaveUpdateEmitsUpdateSQL(): void
    {
        $o = a('ut/sample');
        $o->setData($this->sampleRow(['id' => 77, 'text' => 'old']));
        $o->text = 'new';
        self::assertTrue($o->save());

        $SQL = $this->findSQL($this->db, 'UPDATE "ut_sample"');
        self::assertStringContainsString('UPDATE "ut_sample" SET ', $SQL);
        self::assertStringContainsString('"text"=\'new\'', $SQL);
        self::assertStringEndsWith(' WHERE "id"=77', $SQL);
    }

    public function testSaveRollsBackWhenQueryFails(): void
    {
        $failDb = $this->createDatabaseMock(['query', 'quote', 'quoteIdent', 'lastInsertId', 'beginTransaction', 'commit', 'rollback'], true);
        $failDb->expects($this->any())->method('lastInsertId')->will($this->returnValue(1));
        $failDb->expects($this->any())->method('query')->will($this->returnValue(false));
        $failDb->expects($this->once())->method('rollback');
        $failDb->expects($this->never())->method('commit');

        $o = $this->makeOrmMock($failDb, 'ut/sample', 'ut_sample', [
            'id' => 'bigint,primary,serial',
            'text' => 'string',
        ]);
        $o->text = 'hi';
        self::assertFalse($o->save());
    }

    // ========== 3.1 saveAll pivot branches ==========

    public function testSaveAllObjectPivotInsertsAndDeletes(): void
    {
        $o = a('ut/sample', 100);
        // 库里原有 oid 2/3, 提交保留 3、新增 4、删除 2
        $this->resultRows = [['oid' => 2], ['oid' => 3]];

        $kept = a('ut/sample', 3);
        $added = a('ut/sample', 4);

        self::assertTrue((bool) $o->saveAll('apple', [3 => $kept, 4 => $added]));

        $deleteSQL = $this->findSQL($this->db, 'DELETE FROM "_ut_sample_apple"');
        self::assertSame(
            'DELETE FROM "_ut_sample_apple" WHERE "ut_sample_id"=100 AND "apple_id" IN (2)',
            $deleteSQL
        );
        $insertSQL = $this->findSQL($this->db, 'INSERT INTO "_ut_sample_apple"');
        self::assertSame(
            'INSERT INTO "_ut_sample_apple" ("ut_sample_id", "apple_id") VALUES (100,4)',
            $insertSQL
        );
    }

    public function testSaveAllPolymorphicObjectPivotUsesNameAndId(): void
    {
        $o = a('ut/poly', 100);
        $this->resultRows = [];
        $thing = a('ut/poly', 7);

        // 直接用与 ORMIterator 等价的 key=>object 数组，避免 name=null 迭代器的隐式查询
        self::assertTrue((bool) $o->saveAll('thing', ['ut/poly/7' => $thing]));

        $SQL = $this->findSQL($this->db, 'INSERT INTO "_ut_poly_thing"');
        self::assertSame(
            'INSERT INTO "_ut_poly_thing" ("ut_poly_id", "thing_name", "thing_id") VALUES (100,\'ut/poly\',7)',
            $SQL
        );
    }

    public function testSaveAllScalarFieldPivot(): void
    {
        $o = a('ut/poly', 100);
        $this->resultRows = [['field' => 'old']];

        $tags = new \Gini\Helper\TouchableArray(['kept']);
        self::assertTrue((bool) $o->saveAll('tag', $tags));

        $deleteSQL = $this->findSQL($this->db, 'DELETE FROM "_ut_poly_tag"');
        self::assertSame(
            'DELETE FROM "_ut_poly_tag" WHERE "ut_poly_id"=100 AND "tag" IN (\'old\')',
            $deleteSQL
        );
        $insertSQL = $this->findSQL($this->db, 'INSERT INTO "_ut_poly_tag"');
        self::assertSame(
            'INSERT INTO "_ut_poly_tag" ("ut_poly_id", "tag") VALUES (100,\'kept\')',
            $insertSQL
        );
    }

    // ========== 3.1 removeAll ==========

    public function testRemoveAllDeletesPivotRows(): void
    {
        $o = a('ut/sample', 100);
        self::assertTrue($o->removeAll('apple'));
        self::assertSame(
            'DELETE FROM "_ut_sample_apple" WHERE "ut_sample_id"=100',
            $this->findSQL($this->db, 'DELETE FROM "_ut_sample_apple"')
        );
    }

    public function testRemoveAllWithoutIdReturnsFalse(): void
    {
        $o = a('ut/sample');
        self::assertFalse($o->removeAll('apple'));
    }

    // ========== 3.1 add() three branches + guard ==========

    public function testAddNamedObjectPivot(): void
    {
        $o = a('ut/sample', 100);
        $child = a('ut/sample', 9);
        self::assertSame($o, $o->add('apple', $child));
        self::assertSame(
            'INSERT INTO "_ut_sample_apple" ("ut_sample_id", "apple_id") VALUES (100, 9)',
            $this->findSQL($this->db, 'INSERT INTO "_ut_sample_apple"')
        );
    }

    public function testAddPolymorphicObjectPivot(): void
    {
        $o = a('ut/poly', 100);
        $thing = a('ut/poly', 7);
        $o->add('thing', $thing);
        self::assertSame(
            'INSERT INTO "_ut_poly_thing" ("ut_poly_id", "thing_name", "thing_id") VALUES (100, \'ut/poly\', 7)',
            $this->findSQL($this->db, 'INSERT INTO "_ut_poly_thing"')
        );
    }

    public function testAddScalarFieldPivot(): void
    {
        $o = a('ut/poly', 100);
        $o->add('tag', 'hot');
        self::assertSame(
            'INSERT INTO "_ut_poly_tag" ("ut_poly_id", "tag") VALUES (100, \'hot\')',
            $this->findSQL($this->db, 'INSERT INTO "_ut_poly_tag"')
        );
    }

    public function testAddUnknownManyNameThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        a('ut/sample', 1)->add('ghost', 'x');
    }

    // ========== 3.1 remove() three branches + guard ==========

    public function testRemoveNamedObjectPivot(): void
    {
        $o = a('ut/sample', 100);
        $child = a('ut/sample', 9);
        $o->remove('apple', $child);
        self::assertSame(
            'DELETE FROM "_ut_sample_apple" WHERE "ut_sample_id"=100 AND "apple_id"=9',
            $this->findSQL($this->db, 'DELETE FROM "_ut_sample_apple"')
        );
    }

    public function testRemovePolymorphicObjectPivot(): void
    {
        $o = a('ut/poly', 100);
        $thing = a('ut/poly', 7);
        $o->remove('thing', $thing);
        self::assertSame(
            'DELETE FROM "_ut_poly_thing" WHERE "ut_poly_id"=100 AND "thing_name"=\'ut/poly\' AND "thing_id"=7',
            $this->findSQL($this->db, 'DELETE FROM "_ut_poly_thing"')
        );
    }

    public function testRemoveScalarFieldPivot(): void
    {
        $o = a('ut/poly', 100);
        $o->remove('tag', 'hot');
        self::assertSame(
            'DELETE FROM "_ut_poly_tag" WHERE "ut_poly_id"=100 AND "tag"=\'hot\'',
            $this->findSQL($this->db, 'DELETE FROM "_ut_poly_tag"')
        );
    }

    public function testRemoveUnknownManyNameThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        a('ut/sample', 1)->remove('ghost', 'x');
    }

    // ========== 3.1 __call event dispatch ==========

    public function testMagicCallThrowsWhenNoHandlerBound(): void
    {
        $this->expectException(\BadMethodCallException::class);
        a('ut/sample')->neverBoundUtMethodXyz();
    }

    public function testMagicCallDispatchesThroughInheritanceEventChain(): void
    {
        $method = 'utMethod' . uniqid();
        \Gini\Event::bind("orm[base].call[$method]", function ($event, $object, array $params) {
            self::assertInstanceOf(\Gini\Event::class, $event);
            self::assertSame('ut/sample', $object->name());
            return ['echo' => $params];
        });

        $result = a('ut/sample')->{$method}(1, 'two');
        self::assertSame(['echo' => [1, 'two']], $result);
    }
}
