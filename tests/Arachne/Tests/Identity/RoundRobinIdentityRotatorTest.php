<?php

namespace Arachne\Tests\Identity;

use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayProfile;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;
use Arachne\Identity\RoundRobinIdentityRotator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zend\Diactoros\Request;

class RoundRobinIdentityRotatorTest extends \PHPUnit_Framework_TestCase
{
    public function testSwitchIdentity()
    {
        $identity = new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Arachne');
        $identity2 = new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Arachne2');
        $identity3 = new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Arachne3');
        $identity4 = new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost(),
            new GatewayProfile(['google.com'])), 'Arachne4');
        $collection = new IdentitiesCollection($identity, $identity2, $identity3, $identity4);
        $rotator = new RoundRobinIdentityRotator($collection);
        $request = $this->createMock(Request::class);

        $this->assertEquals($identity, $rotator->getCurrentIdentity());
        $rotator->switchIdentityFor($request);
        $this->assertEquals($identity2, $rotator->getCurrentIdentity());
        $rotator->switchIdentityFor($request);
        $this->assertEquals($identity3, $rotator->getCurrentIdentity());
        $rotator->switchIdentityFor($request);
        $this->assertEquals($identity, $rotator->getCurrentIdentity());

    }

    /**
     * @expectedException \Arachne\Exceptions\NoGatewaysLeftException
     */
    public function testExceptionWhenNoIdentitiesLeft()
    {
        $identity = new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Arachne');
        $identity2 = new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost(),
            new GatewayProfile(['google.com'])), 'Arachne4');
        $collection = new IdentitiesCollection($identity, $identity2);
        $rotator = new RoundRobinIdentityRotator($collection);
        $request = $this->createMock(Request::class);
        $identity->getGateway()->block();
        $rotator->switchIdentityFor($request);
    }
}