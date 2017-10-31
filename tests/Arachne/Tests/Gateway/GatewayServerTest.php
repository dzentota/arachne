<?php

namespace Arachne\Tests\Gateway;

use Arachne\Gateway\GatewayServer;
use Arachne\Gateway\Localhost;

class GatewayServerTest extends \PHPUnit_Framework_TestCase
{
    public function gatewayDataProvider()
    {
        return [
            ['https://198.168.140.84:3128', 'https', '198.168.140.84', 3128, null, null],
            ['socks5://username:password@198.168.140.84:3128', 'socks5', '198.168.140.84', 3128, 'username', 'password']
        ];
    }

    /**
     * @dataProvider gatewayDataProvider
     * @param $string
     * @param $type
     * @param $ip
     * @param $port
     * @param $username
     * @param $password
     */
    public function testInstantiateFromString($string, $type, $ip, $port, $username, $password)
    {
        $gatewayServer = GatewayServer::fromString($string);
        $this->assertEquals($type, $gatewayServer->getType());
        $this->assertEquals($ip, $gatewayServer->getIp());
        $this->assertEquals($port, $gatewayServer->getPort());
        $this->assertEquals($username, $gatewayServer->getUsername());
        $this->assertEquals($password, $gatewayServer->getPassword());
    }

    public function testFromStringToString()
    {
        $gatewayServerUrl = 'https://198.168.140.84:3128';
        $gatewayServer =  GatewayServer::fromString($gatewayServerUrl);
        $this->assertEquals($gatewayServerUrl, (string) $gatewayServer);
    }

    public function testLocalhost()
    {
        $this->assertInstanceOf(Localhost::class, GatewayServer::localhost());
    }

    /**
     * @expectedException \Respect\Validation\Exceptions\ValidationException
     */
    public function testThrowsExceptionOnInvalidType()
    {
        GatewayServer::fromString('ws://198.168.140.84:3128');
    }
}
