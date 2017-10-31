<?php

namespace Arachne\Tests;

use Psr\Log\NullLogger;
use Arachne\Filter\InMemory as InMemoryFilter;
use Arachne\Frontier\FrontierInterface;
use Arachne\Frontier\InMemory as InMemoryFrontier;
use Arachne\Resource;
use Arachne\Scheduler;
use Zend\Diactoros\Request;

class SchedulerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetters()
    {
        $frontier = $this->getMockBuilder(InMemoryFrontier::class)->disableOriginalConstructor()->getMock();
        $filter = $this->getMockBuilder(InMemoryFilter::class)->disableOriginalConstructor()->getMock();
        $logger = new NullLogger();
        $scheduler = new Scheduler($frontier, $filter, $logger);
        $this->assertInstanceOf(InMemoryFrontier::class, $scheduler->getFrontier());
        $this->assertInstanceOf(InMemoryFilter::class, $scheduler->getFilter());
        $this->assertInstanceOf(NullLogger::class, $scheduler->getLogger());
    }

    public function testPopulateSingleItemScheduled()
    {
        $frontier = $this->getMockBuilder(InMemoryFrontier::class)
            ->disableOriginalConstructor()
            ->setMethods(['populate'])
            ->getMock();
        $filter = $this->getMockBuilder(InMemoryFilter::class)->disableOriginalConstructor()->getMock();
        $logger = new NullLogger();

        $scheduler = $this->getMockBuilder(Scheduler::class)
            ->setConstructorArgs([$frontier, $filter, $logger])
            ->setMethods(['isScheduled', 'isVisited', 'markScheduled'])
            ->getMock();

        $item = new Resource(new Request('localhost'), 'test');

        $scheduler->expects($this->once())->method('isScheduled')->with($item)->will($this->returnValue(true));
        $scheduler->expects($this->never())->method('isVisited');
        $scheduler->expects($this->never())->method('markScheduled');
        $frontier->expects($this->never())->method('populate');

        $priority = FrontierInterface::PRIORITY_NORMAL;
        $scheduler->schedule($item, $priority);

    }

    public function testPopulateSingleItemVisited()
    {
        $frontier = $this->getMockBuilder(InMemoryFrontier::class)
            ->disableOriginalConstructor()
            ->setMethods(['populate'])
            ->getMock();
        $filter = $this->getMockBuilder(InMemoryFilter::class)->disableOriginalConstructor()->getMock();
        $logger = new NullLogger();

        $scheduler = $this->getMockBuilder(Scheduler::class)
            ->setConstructorArgs([$frontier, $filter, $logger])
            ->setMethods(['isScheduled', 'isVisited', 'markScheduled'])
            ->getMock();

        $item = new Resource(new Request('localhost'), 'test');

        $scheduler->expects($this->once())->method('isScheduled')->with($item)->will($this->returnValue(false));
        $scheduler->expects($this->once())->method('isVisited')->with($item)->will($this->returnValue(true));
        $scheduler->expects($this->never())->method('markScheduled');
        $frontier->expects($this->never())->method('populate');

        $priority = FrontierInterface::PRIORITY_NORMAL;
        $scheduler->schedule($item, $priority);
    }

    public function testPopulateSingleMarkScheduled()
    {
        $frontier = $this->getMockBuilder(InMemoryFrontier::class)
            ->disableOriginalConstructor()
            ->setMethods(['populate'])
            ->getMock();
        $filter = $this->getMockBuilder(InMemoryFilter::class)->disableOriginalConstructor()->getMock();
        $logger = new NullLogger();

        $scheduler = $this->getMockBuilder(Scheduler::class)
            ->setConstructorArgs([$frontier, $filter, $logger])
            ->setMethods(['isScheduled', 'isVisited', 'markScheduled'])
            ->getMock();

        $item = new Resource(new Request('localhost'), 'test');

        $scheduler->expects($this->once())->method('isScheduled')->with($item)->will($this->returnValue(false));
        $scheduler->expects($this->once())->method('isVisited')->with($item)->will($this->returnValue(false));
        $scheduler->expects($this->once())->method('markScheduled');
        $frontier->expects($this->once())->method('populate');

        $priority = FrontierInterface::PRIORITY_NORMAL;
        $scheduler->schedule($item, $priority);
    }


}
