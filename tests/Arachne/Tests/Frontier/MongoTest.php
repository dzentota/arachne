<?php

namespace Arachne\Tests\Frontier;

use Arachne\Frontier\FrontierInterface;
use Arachne\Frontier\Mongo;
use Arachne\HttpResource;
use Zend\Diactoros\Request;

class MongoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Mongo
     */
    static protected $mongo;

    public static function setUpBeforeClass()
    {
        $client = new \MongoDB\Client();
        $client->scraper_test->drop();
        self::$mongo = new Mongo($client, 'scraper_test');
    }

    public static function tearDownAfterClass()
    {
        $client = new \MongoDB\Client();
        $client->scraper_test->drop();
    }

    public function testQueue()
    {
        $resource = new HttpResource(new Request('/', 'GET'), 'foo');
        $resource2 = new HttpResource(new Request('/', 'GET'), 'bar');
        $resource3 = new HttpResource(new Request('/', 'GET'), 'baz');
        self::$mongo->populate($resource);
        self::$mongo->populate($resource2);

        $resourceFromFrontier = self::$mongo->nextItem();
        $this->assertEquals($resource->getType(), $resourceFromFrontier->getType());
        $this->assertEquals($resource->getUrl(), $resourceFromFrontier->getUrl());

        self::$mongo->populate($resource3, FrontierInterface::PRIORITY_HIGH);

        $resourceFromFrontier = self::$mongo->nextItem();
        $this->assertEquals($resource3->getType(), $resourceFromFrontier->getType());
        $this->assertEquals($resource3->getUrl(), $resourceFromFrontier->getUrl());

        self::$mongo->clear();
        $this->assertNull(self::$mongo->nextItem());
    }
}
