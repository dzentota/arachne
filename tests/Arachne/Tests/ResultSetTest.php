<?php

namespace Arachne\Tests;

use Http\Message\MessageFactory\DiactorosMessageFactory;
use Arachne\Frontier\FrontierInterface;
use Arachne\Item;
use Arachne\Resource;
use Arachne\ResultSet;
use Zend\Diactoros\Request;

class ResultSetTest extends \PHPUnit_Framework_TestCase
{
    public function testResources()
    {
        $resource = new Resource(new Request('localhost', 'GET'), 'test');
        $requestFactory = new DiactorosMessageFactory();
        $resultSet = new ResultSet($resource, $requestFactory);
        $this->assertEquals($resource, $resultSet->getResource());

    }

    public function testParsedResources()
    {
        $resource = new Resource(new Request('localhost', 'GET'), 'test');
        $requestFactory = new DiactorosMessageFactory();
        $resultSet = new ResultSet($resource, $requestFactory);

        $resultSet->addResource('test', 'http://localhost/foo/bar');
        $resultSet->addResource('test2', 'http://localhost/abc/xyz', null, 'POST');
        $resultSet->addHighPriorityResource('test3', 'http://localhost/hello/world.html', null, 'PUT', 'foo bar');

        $newResources = $resultSet->getNewResources();
        $newResourcesWithNormalPriority = $newResources[FrontierInterface::PRIORITY_NORMAL];
        $newResourcesWithHighPriority = $newResources[FrontierInterface::PRIORITY_HIGH];

        $this->assertEquals(2, count($newResourcesWithNormalPriority));
        $this->assertEquals(1, count($newResourcesWithHighPriority));

        $item = new Item(['id' => 123]);
        $resultSet->addResource('test4', 'http://localhost', $item);
        $resultSet->addHighPriorityResource('test5', 'http://localhost', $item);

        $relatedResources = $resultSet->getRelatedResources();
        $relatedResourcesWithNormalPriority = $relatedResources[FrontierInterface::PRIORITY_NORMAL];
        $relatedResourcesWithHighPriority = $relatedResources[FrontierInterface::PRIORITY_HIGH];

        $this->assertEquals(1, count($relatedResourcesWithNormalPriority));
        $this->assertEquals(1, count($relatedResourcesWithHighPriority));

        $parsedResources = $resultSet->getParsedResources();
        $parsedResourcesWithNormalPriority = $parsedResources[FrontierInterface::PRIORITY_NORMAL];
        $parsedResourcesWithHighPriority = $parsedResources[FrontierInterface::PRIORITY_HIGH];

        $this->assertEquals(3, count($parsedResourcesWithNormalPriority));
        $this->assertEquals(2, count($parsedResourcesWithHighPriority));

        $this->assertEquals(array_merge($newResourcesWithNormalPriority, $relatedResourcesWithNormalPriority),
            $parsedResourcesWithNormalPriority);
        $this->assertEquals(array_merge($newResourcesWithHighPriority, $relatedResourcesWithHighPriority),
            $parsedResourcesWithHighPriority);
    }

    public function testItems()
    {
        $resource = new Resource(new Request('localhost', 'GET'), 'test');
        $resource->setMeta(['related_type' => 'related type', 'related_id'=>123]);
        $requestFactory = new DiactorosMessageFactory();
        $resultSet = new ResultSet($resource, $requestFactory);

        $item1 = new Item(['id' => uniqid(), 'type' => 'related type']);
        $item2 = new Item(['id' => uniqid()]);
        $resultSet->addItem($item1);
        $itemWithNewId = $resultSet->getItems()[0];
        $this->assertEquals(123, $itemWithNewId->id);

        $resultSet->addItem($item2);
        $this->assertEquals(2, count($resultSet->getItems()));
    }

    public function testBlob()
    {
        $resource = new Resource(new Request('localhost', 'GET'), 'test');
        $requestFactory = new DiactorosMessageFactory();
        $resultSet = new ResultSet($resource, $requestFactory);

        $this->assertFalse($resultSet->isBlob());
        $resultSet->markAsBlob();
        $this->assertTrue($resultSet->isBlob());
    }

    public function testPackInBatch()
    {
        $resource = new Resource(new Request('localhost', 'GET'), 'test');
        $requestFactory = new DiactorosMessageFactory();
        $resultSet = new ResultSet($resource, $requestFactory);

        $resultSet->addResource('test', 'http://localhost/foo/bar');
        $resultSet->addResource('test2', 'http://localhost/abc/xyz', null, 'POST');
        $resultSet->addHighPriorityResource('test3', 'http://localhost/hello/world.html', null, 'PUT', 'foo bar');

        $newResources = $resultSet->getNewResources();
        $newResourcesWithNormalPriority = $newResources[FrontierInterface::PRIORITY_NORMAL];
        $newResourcesWithHighPriority = $newResources[FrontierInterface::PRIORITY_HIGH];

        $this->assertEquals(2, count($newResourcesWithNormalPriority));
        $this->assertEquals(1, count($newResourcesWithHighPriority));

        $item = new Item(['id' => 123]);
        $resultSet->addResource('test4', 'http://localhost', $item);
        $resultSet->addHighPriorityResource('test5', 'http://localhost', $item);

        $relatedResources = $resultSet->getRelatedResources();
        $relatedResourcesWithNormalPriority = $relatedResources[FrontierInterface::PRIORITY_NORMAL];
        $relatedResourcesWithHighPriority = $relatedResources[FrontierInterface::PRIORITY_HIGH];

        $this->assertEquals(1, count($relatedResourcesWithNormalPriority));
        $this->assertEquals(1, count($relatedResourcesWithHighPriority));

        $parsedResources = $resultSet->getParsedResources();
        $parsedResourcesWithNormalPriority = $parsedResources[FrontierInterface::PRIORITY_NORMAL];
        $parsedResourcesWithHighPriority = $parsedResources[FrontierInterface::PRIORITY_HIGH];

        $this->assertEquals(3, count($parsedResourcesWithNormalPriority));
        $this->assertEquals(2, count($parsedResourcesWithHighPriority));

        $this->assertEquals(array_merge($newResourcesWithNormalPriority, $relatedResourcesWithNormalPriority),
            $parsedResourcesWithNormalPriority);
        $this->assertEquals(array_merge($newResourcesWithHighPriority, $relatedResourcesWithHighPriority),
            $parsedResourcesWithHighPriority);
    }
}
