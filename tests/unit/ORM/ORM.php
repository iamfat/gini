<?php

namespace Gini\PHPUnit\ORM;

require_once __DIR__.'/../gini.php';

class ORM extends \Gini\PHPUnit\CLI
{
    public function setUp()
    {
        parent::setUp();

        class_exists('\Gini\Those');

        $db = $this->getMockBuilder('\Gini\Database')
             ->setMethods(['query', 'quote', 'quoteIdent'])
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

                return '"'.addslashes($s).'"';
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

                return '\''.addslashes($s).'\'';
            }));

        \Gini\IoC::bind('\Gini\ORM\UTSample', function ($criteria = null) use ($db) {

            $o = $this->getMockBuilder('\Gini\ORM\Object')
                 ->setMethods(['db', 'properties', 'name', 'tableName'])
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

            $properties = [
                'id' => 'bigint,primary,serial',
                '_extra' => 'array',
                'object' => 'object',
                'sample' => 'object:utsample',
                'number' => 'int',
                'text' => 'string',
            ];

            $o->expects($this->any())
                ->method('properties')
                ->will($this->returnValue($properties));

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

    // @disabled
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
                $this->assertEquals($SQL,
                    'INSERT INTO "utsample" ("_extra","object_name","object_id","sample_id") VALUES(\'{}\',\'utsample\',10,0)');
            }));

        $o1->save();
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
}
