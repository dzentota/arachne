<?php

namespace Arachne\Tests\Filter;

use Arachne\Filter\Mongo;

class MongoTest extends \PHPUnit_Framework_TestCase
{
    static protected $mongo;
    protected $resource;

    public static function setUpBeforeClass()
    {
        $client = new \MongoDB\Client('mongodb://mongo', ['username' => 'root', 'password' => 'root']);
        $client->scraper_test->drop();
        self::$mongo = new Mongo($client, 'scraper_test');
    }

    public static function tearDownAfterClass()
    {
        $client = new \MongoDB\Client('mongodb://mongo', ['username' => 'root', 'password' => 'root']);
        $client->scraper_test->drop();
    }

    protected function setUp()
    {
        $resource = $this->getMockBuilder('\\Arachne\\HttpResource')->disableOriginalConstructor()->getMock();
        $resource->expects($this->any())->method('getHash')->will($this->returnValue(sha1(123)));
        $this->resource = $resource;
        self::$mongo->add('foo', $resource);
    }

    protected function tearDown()
    {
        self::$mongo->clear('foo');
    }

    public function testAddExists()
    {
        $this->assertTrue(self::$mongo->exists('foo', $this->resource));
        $resource = $this->getMockBuilder('\\Arachne\\HttpResource')->disableOriginalConstructor()->getMock();
        $resource->expects($this->any())->method('getHash')->will($this->returnValue(sha1(555)));
        $this->assertFalse(self::$mongo->exists('foo', $resource));
    }

    public function testRemoveCountClear()
    {
        $this->assertEquals(1, self::$mongo->count('foo'));
        $resource = $this->getMockBuilder('\\Arachne\\HttpResource')->disableOriginalConstructor()->getMock();
        $resource->expects($this->any())->method('getHash')->will($this->returnValue(sha1(555)));
        self::$mongo->add('foo', $resource);
        $this->assertEquals(2, self::$mongo->count('foo'));
        self::$mongo->remove('foo', $resource);
        $this->assertEquals(1, self::$mongo->count('foo'));
        self::$mongo->clear('foo');
        $this->assertEquals(0, self::$mongo->count('foo'));
    }
}
