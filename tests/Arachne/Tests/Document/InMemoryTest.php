<?php

namespace Arachne\Tests\Document;

use Arachne\Document\InMemory;

class InMemoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InMemory
     */
    static private $storage;
    static protected $initData = ['foo'=> 'bar', 'baz'=> '123'];

    protected function setUp()
    {
        self::$storage = new InMemory();
        self::$storage->create('foo', '123abc', self::$initData);
    }

    protected function tearDown()
    {
        self::$storage->clear();
    }

    public function testCreate()
    {
        $this->assertEquals(self::$initData, self::$storage->get('foo', '123abc'));
    }

    public function testUpdate()
    {
        $newData = ['Arachne'=> 'scraper', 'foo'=>'hello'];
        self::$storage->update('foo', '123abc', $newData);
        $updatedData = self::$storage->get('foo', '123abc');
        $this->assertArrayHasKey('Arachne', $updatedData);
        $this->assertEquals($updatedData['Arachne'], 'scraper');
        $this->assertEquals($updatedData['foo'], 'hello');
    }

    public function testDelete()
    {
        self::$storage->delete('foo', '123abc');
        $this->assertNull(self::$storage->get('foo', '123abc'));
    }


    public function testGetIterator()
    {
        $data = ['bar'=>'baz'];
        self::$storage->create('foo2', '555', $data);
        $iterator = self::$storage->getIterator('foo');
        $docs = [];
        foreach ($iterator as $doc) {
            $docs[] = $doc;
        }
        $this->assertEquals([self::$initData], $docs, 'Documents with type foo');

        $iterator = self::$storage->getIterator();
        $docs = [];
        foreach ($iterator as $doc) {
            $docs[] = $doc;
        }
        $this->assertEquals([self::$initData, $data], $docs, 'Documents with all types');
    }

    public function testCountAndClear()
    {
        $this->assertEquals(1, self::$storage->count('foo'));
        $data = ['bar'=>'baz'];
        self::$storage->create('foo2', '555', $data);
        $this->assertEquals(2, self::$storage->count());
        self::$storage->clear();
        $this->assertEquals(0, self::$storage->count());
    }

    public function testGetTypes()
    {
        $data = ['bar'=>'baz'];
        self::$storage->create('foo2', '555', $data);
        $this->assertEquals(['foo', 'foo2'], self::$storage->getTypes());
    }
}
