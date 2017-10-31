<?php

namespace Arachne\Tests\Identity;

use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;

class IdentitiesCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testToArray()
    {
        $identity = $this->createMock(Identity::class);
        $collection = new IdentitiesCollection($identity);
        $this->assertEquals([$identity], $collection->toArray());
    }

    public function testCount()
    {
        $identity = $this->createMock(Identity::class);
        $identity2 = $this->createMock(Identity::class);
        $collection = new IdentitiesCollection($identity, $identity2);
        $this->assertEquals(2, $collection->count());
        $this->assertEquals(count($collection), $collection->count());
    }

    public function testAdd()
    {
        $identity = $this->createMock(Identity::class);
        $identity2 = $this->createMock(Identity::class);
        $collection = new IdentitiesCollection($identity);
        $collection->add($identity2);
        $this->assertEquals([$identity, $identity2], $collection->toArray());
    }

    public function testIterator()
    {
        $collection = new IdentitiesCollection();
        $this->assertInstanceOf(\Iterator::class, $collection->getIterator());
    }

    public function testSlice()
    {
        $identity = $this->createMock(Identity::class);
        $identity2 = $this->createMock(Identity::class);
        $identity3 = $this->createMock(Identity::class);
        $identity4 = $this->createMock(Identity::class);

        $collection = new IdentitiesCollection($identity, $identity2, $identity3, $identity4);
        $slice = $collection->slice(1);
        $this->assertEquals([$identity2, $identity3, $identity4], $slice->toArray());

        $slice2 = $collection->slice(0,2);
        $this->assertEquals([$identity, $identity2], $slice2->toArray());
    }

}