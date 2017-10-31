<?php

namespace Arachne\Tests\Client;

use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\IdentityRotator;
use Arachne\Identity\RoundRobinIdentityRotator;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use Arachne\Client\GuzzleClient;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

class GuzzleClientTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultHttpClient()
    {
        $dispatcher = new EventDispatcher();
        $identityRotator = new RoundRobinIdentityRotator(new IdentitiesCollection());
        $guzzleDefaultClient = new GuzzleClient($dispatcher, $identityRotator);
        $httpClient = $guzzleDefaultClient->getHttpClient();
        $this->assertInstanceOf(ClientInterface::class, $httpClient);
    }

    public function testHttpClient()
    {

        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock2 = $this->createMock(\GuzzleHttp\Client::class);
        $dispatcher = new EventDispatcher();
        $identityRotator = new RoundRobinIdentityRotator(new IdentitiesCollection());
        $guzzleClient = new GuzzleClient($dispatcher, $identityRotator, $httpClientMock);
        $this->assertSame($httpClientMock, $guzzleClient->getHttpClient());

        $guzzleClient->setHttpClient($httpClientMock2);
        $this->assertSame($httpClientMock2, $guzzleClient->getHttpClient());

    }

    public function sendRequestDataProvider()
    {
        return [
            [
                new Request('http://localhost/foo/bar'),
                [],
                new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Arachne'),
                [
                    'allow_redirects' => ['referer' => true],
                    'headers' => ['User-Agent' => 'Arachne'],
                    'cookies' => new CookieJar()
                ]
            ],
            [
                new Request('http://localhost/foo/bar'),
                [
                    'allow_redirects' => ['referer' => false],
                    'headers' => ['User-Agent' => 'My Arachne', 'foo' => 'bar'],
                    'cookies' => false
                ],
                new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Arachne'),
                [
                    'allow_redirects' => ['referer' => false],
                    'headers' => ['User-Agent' => 'My Arachne', 'foo' => 'bar'],
                    'cookies' => false
                ]
            ],
            [
                new Request('http://localhost/foo/bar'),
                ['headers' => ['foo' => 'bar']],
                new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Identity Arachne', [],
                    true, false, true),
                [
                    'allow_redirects' => ['referer' => true],
                    'headers' => ['User-Agent' => 'Identity Arachne', 'foo' => 'bar'],
                    'cookies' => new CookieJar()
                ]
            ],
            [
                new Request('http://localhost/foo/bar'),
                [],
                new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Identity Arachne',
                    ['bar' => 'baz'], false, false, true),
                [
                    'allow_redirects' => ['referer' => true],
                    'headers' => ['User-Agent' => 'Identity Arachne', 'bar' => 'baz'],
                    'cookies' => false
                ]
            ],
        ];
    }

    /**
     * @dataProvider sendRequestDataProvider
     */
    public function testSuccessSendRequest($request, $config, $identity, $expectedConfig)
    {
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $response = new Response();
        $response->getBody()->write('content');

        $httpClientMock->expects($this->once())->method('send')
            ->with($request, $expectedConfig)
            ->will($this->returnValue($response));
        $identityRotator = $this->getMockBuilder(IdentityRotator::class)
            ->setConstructorArgs([new IdentitiesCollection($identity)])
            ->getMock();
        /**
         */
        $identityRotator->expects($this->once())->method('getCurrentIdentity')->will($this->returnValue($identity));

        $identityRotator->expects($this->once())->method('switchIdentityFor')
            ->with($this->equalTo($request))
            ->will($this->returnValue($identity));
        $identityRotator->expects($this->once())->method('evaluateResult')->with($response);
        $dispatcher = new EventDispatcher();

        $guzzleClient = new GuzzleClient($dispatcher, $identityRotator, $httpClientMock);
        $guzzleClient->sendRequest($request, $config);
    }

    /**
     * @expectedException \LogicException
     */
    public function testExceptionOnJsEnabled()
    {
        $identity = new Identity(new Gateway(new EventDispatcher(), GatewayServer::localhost()), 'Arachne', [], true,
            true);
        $identityRotator = new RoundRobinIdentityRotator(new IdentitiesCollection($identity));
        $dispatcher = new EventDispatcher();
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);

        $guzzleClient = new GuzzleClient($dispatcher, $identityRotator, $httpClientMock);
        $guzzleClient->sendRequest(new Request());
    }

}
