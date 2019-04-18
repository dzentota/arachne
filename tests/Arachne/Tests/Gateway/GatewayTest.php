<?php

namespace Arachne\Tests\Gateway;

use Arachne\Gateway\Events\GatewayBlocked;
use Arachne\Gateway\Events\GatewayFailed;
use Arachne\Gateway\Events\GatewayRequested;
use Arachne\Gateway\Events\GatewaySucceeded;
use Arachne\Gateway\Events\GatewayUnblocked;
use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayProfile;
use Arachne\Gateway\GatewayServer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zend\Diactoros\Request;

class GatewayTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $gatewayServer = GatewayServer::localhost();
        $gateway = new Gateway(new EventDispatcher(), $gatewayServer);
        $this->assertSame($gatewayServer, $gateway->getGatewayServer());
        $this->assertInstanceOf(GatewayProfile::class, $gateway->getGatewayProfile());

        $this->assertFalse($gateway->isBlocked());
        $this->assertEquals(0, $gateway->getCurrentTotalFails());
        $this->assertEquals(-1, $gateway->getMaxTotalFails());
        $this->assertEquals(0, $gateway->getCurrentConsecutiveFails());
        $this->assertEquals(3, $gateway->getMaxConsecutiveFails());
        $this->assertEquals(0, $gateway->getTotalRequests());
    }

    public function testGatewayIsApplicableToAllRequestsByDefault()
    {
        $request = new Request('localhost', 'GET');
        $request2 = new Request('http://example.com/any/route?foo=bar', 'POST');
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost());

        $this->assertTrue($gateway->isApplicableTo($request));
        $this->assertTrue($gateway->isApplicableTo($request2));
    }

    public function testGatewayIsNotApplicableToBlackList()
    {
        $request = new Request('http://example.com/any/route?foo=bar', 'POST');
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost(), new GatewayProfile(null, [
            'example\.com'
        ]));
        $this->assertFalse($gateway->isApplicableTo($request));
    }

    public function testGatewayIsApplicableToWhiteList()
    {
        $request = new Request('http://example.com/any/route?foo=bar', 'POST');
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost(), new GatewayProfile([
            'example\.com'
        ]));
        $this->assertTrue($gateway->isApplicableTo($request));
        $request2 = new Request('http://google.com', 'GET');
        $this->assertFalse($gateway->isApplicableTo($request2));
    }

    public function isUsableDataProvider()
    {
        return [
            [true, true, false, false],
            [false, false, true, true],
            [false, true, false, true],
            [false, true, true, false],
            [false, true, true, true],
        ];
    }

    /**
     * @dataProvider isUsableDataProvider
     * @param $expected
     * @param $isApplicable
     * @param $isBlocked
     * @param $hasTooManyFails
     */
    public function isUsableFor($expected, $isApplicable, $isBlocked, $hasTooManyFails)
    {
        $request = new Request('http://example.com/any/route?foo=bar', 'POST');
        $gateway = $this->getMockBuilder(Gateway::class)
        ->disableOriginalConstructor()
        ->setMethods(['isApplicableTo', 'isBlocked', 'hasTooManyFails'])
            ->getMock();
        $gateway->expects($this->any())
            ->method('isApplicableTo')
            ->will($this->returnValue($isApplicable));

        $gateway->expects($this->any())
            ->method('isBlocked')
            ->will($this->returnValue($isBlocked));

        $gateway->expects($this->any())
            ->method('hasTooManyFails')
            ->will($this->returnValue($hasTooManyFails));

        $this->assertEquals($expected, $gateway->isUsableFor($request));
    }

    public function testGatewayFailed()
    {
        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();
        $gatewayServer = GatewayServer::localhost();
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(GatewayFailed::name, new GatewayFailed($gatewayServer));

        $gateway = new Gateway($eventDispatcher, $gatewayServer);
        $gateway->failed();
        $this->assertEquals(1, $gateway->getCurrentConsecutiveFails());
        $this->assertEquals(1, $gateway->getCurrentTotalFails());
    }

    public function testGatewayRequested()
    {
        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();
        $gatewayServer = GatewayServer::localhost();
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(GatewayRequested::name, new GatewayRequested($gatewayServer));

        $gateway = new Gateway($eventDispatcher, $gatewayServer);
        $gateway->requested();
        $this->assertEquals(1, $gateway->getTotalRequests());
    }

    public function testGatewaySucceeded()
    {
        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();
        $gatewayServer = GatewayServer::localhost();
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(GatewaySucceeded::name, new GatewaySucceeded($gatewayServer));

        $gateway = new Gateway($eventDispatcher, $gatewayServer);
        $gateway->setCurrentConsecutiveFails(10);
        $gateway->succeeded();
        $this->assertEquals(0, $gateway->getCurrentConsecutiveFails());
    }

    public function testBlock()
    {
        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();
        $gatewayServer = GatewayServer::localhost();
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(GatewayBlocked::name, new GatewayBlocked($gatewayServer));

        $gateway = new Gateway($eventDispatcher, $gatewayServer);
        try {
            $gateway->block();
        } catch (\Arachne\Exceptions\GatewayException $exception) {
            $this->assertTrue($gateway->isBlocked());
        }
    }

    public function testUnblock()
    {
        $eventDispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->setMethods(['dispatch'])
            ->getMock();
        $gatewayServer = GatewayServer::localhost();
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(GatewayUnblocked::name, new GatewayUnblocked($gatewayServer));

        $gateway = new Gateway($eventDispatcher, $gatewayServer);
        $gateway->unblock();
        $this->assertFalse($gateway->isBlocked());
    }

    public function hasTooManyFailsDataProvider()
    {
        return [
            [true, true, false],
            [true, false, true],
            [false, false, false],
        ];
    }

    /**
     * @dataProvider hasTooManyFailsDataProvider
     * @param $expected
     * @param $hasTooManyConsecutiveFails
     * @param $hasTooManyTotalFails
     */
    public function testHasTooManyFails($expected, $hasTooManyConsecutiveFails, $hasTooManyTotalFails)
    {
        $gateway = $this->getMockBuilder(Gateway::class)
            ->disableOriginalConstructor()
            ->setMethods(['hasTooManyConsecutiveFails', 'hasTooManyTotalFails'])
            ->getMock();

        $gateway->expects($this->any())
            ->method('hasTooManyConsecutiveFails')
            ->will($this->returnValue($hasTooManyConsecutiveFails));
        $gateway->expects($this->any())
            ->method('hasTooManyTotalFails')
            ->will($this->returnValue($hasTooManyTotalFails));

        $this->assertEquals($expected, $gateway->hasTooManyFails());
    }

    public function testHasToManyConsecutiveFails()
    {
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost());
        $gateway->setMaxConsecutiveFails(2);
        $gateway->setCurrentConsecutiveFails(3);
        $this->assertTrue($gateway->hasTooManyConsecutiveFails());
        $gateway->setMaxConsecutiveFails(5);
        $this->assertFalse($gateway->hasTooManyConsecutiveFails());
    }

    public function testHasTooManyTotalFails()
    {
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost());
        $gateway->setMaxTotalFails(2);
        $gateway->setCurrentTotalFails(3);
        $this->assertTrue($gateway->hasTooManyTotalFails());
        $gateway->setMaxTotalFails(5);
        $this->assertFalse($gateway->hasTooManyTotalFails());
    }
}
