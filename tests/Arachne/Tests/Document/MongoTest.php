<?php

namespace Arachne\Tests\Document;

use Arachne\Document\Mongo;

class MongoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Mongo
     */
    static protected $mongo;
    static protected $initData = ['foo'=> 'bar', 'baz'=> '123'];

    public static function setUpBeforeClass()
    {
        $client = new \MongoDB\Client();
        $client->scraper_test->documents->drop();
        self::$mongo = new Mongo($client, 'scraper_test');
    }

    public static function tearDownAfterClass()
    {
        $client = new \MongoDB\Client();
        $client->scraper_test->documents->drop();
    }

    protected function setUp()
    {
        self::$mongo->create('foo', '123abc', self::$initData);
    }

    protected function tearDown()
    {
        self::$mongo->clear();
    }

    public function testCreate()
    {
        $this->assertEquals(self::$initData, self::$mongo->get('foo', '123abc'));
    }

    public function testUpdate()
    {
        $newData = ['Arachne'=> 'scraper', 'foo'=>'hello'];
        self::$mongo->update('foo', '123abc', $newData);
        $updatedData = self::$mongo->get('foo', '123abc');
        $this->assertArrayHasKey('Arachne', $updatedData);
        $this->assertEquals($updatedData['Arachne'], 'scraper');
        $this->assertEquals($updatedData['foo'], 'hello');
    }

    public function testDelete()
    {
        self::$mongo->delete('foo', '123abc');
        $this->assertNull(self::$mongo->get('foo', '123abc'));
    }


    public function testGetIterator()
    {
        $data = ['bar'=>'baz'];
        self::$mongo->create('foo2', '555', $data);
        $iterator = self::$mongo->getIterator('foo');
        $docs = [];
        foreach ($iterator as $doc) {
            $docs[] = (array) $doc;
        }
        $expected = ['foo'=> 'bar', 'baz'=> '123'];

        $this->assertEquals([$expected], $docs, 'Documents with type foo');

        $iterator = self::$mongo->getIterator();
        $docs = [];
        foreach ($iterator as $doc) {
            $docs[] = (array) $doc;
        }
        $expected = [['foo'=> 'bar', 'baz'=> '123'],['bar'=>'baz']];
        $this->assertEquals($expected, $docs, 'Documents with all types');
    }

    public function testCountAndClear()
    {
        $this->assertEquals(1, self::$mongo->count('foo'));
        $data = ['bar'=>'baz'];
        self::$mongo->create('foo2', '555', $data);
        $this->assertEquals(2, self::$mongo->count());
        self::$mongo->clear();
        $this->assertEquals(0, self::$mongo->count());
    }

    public function testGetTypes()
    {
        $data = ['bar'=>'baz'];
        self::$mongo->create('foo2', '555', $data);
        $this->assertEquals(['foo', 'foo2'], self::$mongo->getTypes());
    }

    public function testExists()
    {
        $this->assertTrue(self::$mongo->exists('foo', '123abc'));
        self::$mongo->clear();
        $this->assertFalse(self::$mongo->exists('foo', '123abc'));
    }
}
