<?php

namespace Gini\PHPUnit\Those;

class SubField extends \Gini\PHPUnit\TestCase\CLI
{
    public function setUp(): void
    {
        parent::setUp();

        $db = self::getMockBuilder('\Gini\Database')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['query', 'quote', 'quoteIdent', 'ident'])
            ->disableOriginalConstructor()
            ->getMock();

        $query = self::getMockBuilder('\Gini\Database\Statement')
            ->setMockClassName('MOBJ_' . uniqid())
            ->setMethods(['row'])
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
            ->method('ident')
            ->will($this->returnCallback(
                function ($s) use ($db) {
                    $args = func_get_args();
                    $ident = array();
                    foreach ($args as $arg) {
                        $ident[] = $db->quoteIdent($arg);
                    }

                    return implode('.', $ident);
                }
            ));


        $db->expects($this->any())
            ->method('query')
            ->will($this->returnCallback(function ($s, $s1, $s2) use ($query) {
                return $query;
            }));

        \Gini\IoC::bind('\Gini\ORM\Company', function () use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('company'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('company'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'name' => 'string:50',
                    'type' => 'object:company/type'
                ]));

            return $o;
        });

        \Gini\IoC::bind('\Gini\ORM\Company\Type', function () use ($db) {
            $o = $this->getMockBuilder('\Gini\ORM\Base')
                ->setMockClassName('MOBJ_' . uniqid())
                ->setMethods(['db', 'properties', 'name', 'tableName'])
                ->disableOriginalConstructor()
                ->getMock();

            $o->expects($this->any())
                ->method('db')
                ->will($this->returnValue($db));

            $o->expects($this->any())
                ->method('name')
                ->will($this->returnValue('company/type'));

            $o->expects($this->any())
                ->method('tableName')
                ->will($this->returnValue('company_type'));

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue([
                    'id' => 'bigint,pimary,serial',
                    '_extra' => 'array',
                    'name' => 'string:50'
                ]));

            return $o;
        });
    }

    public function tearDown(): void
    {
        \Gini\IoC::clear('\Gini\ORM\Company\Type');
        \Gini\IoC::clear('\Gini\ORM\Company');
        parent::tearDown();
    }

    public function testFieldOfField()
    {
        \Gini\Those::reset();
        $those = those('company')->whose('type.name')->is('test');
        $those->makeSQL();
        self::assertEquals('SELECT DISTINCT "t0"."id","t0"."_extra","t0"."name","t0"."type_id" FROM "company" AS "t0" LEFT JOIN "company_type" AS "t1" ON "t0"."type_id"="t1"."id" WHERE "t1"."name"=\'test\'', $those->SQL());
    }
}
