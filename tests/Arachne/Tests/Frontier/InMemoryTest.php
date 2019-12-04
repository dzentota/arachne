<?php

namespace Arachne\Tests\Frontier;

use Arachne\Frontier\FrontierInterface;
use Arachne\Frontier\InMemory;
use Arachne\HttpResource;
use Arachne\Frontier\InMemory\OrderedPriorityQueue;
use Zend\Diactoros\Request;

class InMemoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InMemory
     */
    static protected $storage;

    public static function setUpBeforeClass()
    {
        self::$storage = new InMemory(new OrderedPriorityQueue());
    }

    public function testQueue()
    {
        $this->assertEquals(0, self::$storage->count());
        $resource = new HttpResource(new Request('/', 'GET'), 'foo');
        $resource2 = new HttpResource(new Request('/', 'GET'), 'bar');
        $resource3 = new HttpResource(new Request('/', 'GET'), 'baz');
        self::$storage->populate($resource);
        self::$storage->populate($resource2);

        $this->assertEquals(2, self::$storage->count());
        $resourceFromFrontier = self::$storage->nextItem();
        $this->assertEquals($resource->getType(), $resourceFromFrontier->getType());
        $this->assertEquals($resource->getUrl(), $resourceFromFrontier->getUrl());
        $this->assertEquals(1, self::$storage->count());

        self::$storage->populate($resource3, FrontierInterface::PRIORITY_HIGH);

        $resourceFromFrontier = self::$storage->nextItem();
        $this->assertEquals($resource3->getType(), $resourceFromFrontier->getType());
        $this->assertEquals($resource3->getUrl(), $resourceFromFrontier->getUrl());

        self::$storage->clear();
        $this->assertEquals(0, self::$storage->count());
    }
}
