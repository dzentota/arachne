<?php

namespace Arachne\Tests;

use Arachne\BatchResource;
use Arachne\Resource;
use Zend\Diactoros\Request;

class BatchResourceTest extends \PHPUnit_Framework_TestCase
{
    public function testBatch()
    {
        $batch = new BatchResource();
        $resource1 = new Resource(new Request('http://localhost/foo/bar', 'GET'), 'test1');
        $resource2 = new Resource(new Request('http://localhost/xxx/zzz', 'GET'), 'test2');
        $resource3 = new Resource(new Request('http://localhost/abc/zzz', 'GET'), 'test3');

        $batch->addResources($resource1, $resource2);

        $this->assertEquals(2, $batch->count());
        $this->assertEquals([$resource1->getHash() => $resource1, $resource2->getHash() => $resource2],
            $batch->getResources());

        $batch->addResource($resource3);
        $this->assertEquals(3, $batch->count());

        $batch->removeResource($resource1);
        $this->assertEquals(2, $batch->count());
        $this->assertEquals([$resource2->getHash() => $resource2, $resource3->getHash() => $resource3],
            $batch->getResources());

        $serialized = serialize($batch);
        $unserialized =  unserialize($serialized);
        $this->assertEquals($batch->count(), $unserialized->count());

        $batchResources = $batch->getResources();
        $unserializedResources = $unserialized->getResources();

        foreach ($batchResources as $i => $batchResource) {
            $this->assertEquals($batchResource->getType(), $unserializedResources[$i]->getType());
            $this->assertEquals($batchResource->getUrl(), $unserializedResources[$i]->getUrl());
            $this->assertEquals($batchResource->getHash(), $unserializedResources[$i]->getHash());

        }
    }
}

