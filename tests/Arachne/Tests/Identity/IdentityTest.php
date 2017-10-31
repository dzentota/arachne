<?php

namespace Arachne\Tests\Identity;

use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\Identity;
use Symfony\Component\EventDispatcher\EventDispatcher;

class IdentityTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $gateway = $this->createMock(Gateway::class);
        $userAgent = 'Arachne';
        $identity = new Identity($gateway, $userAgent);
        $this->assertEquals($gateway, $identity->getGateway());
        $this->assertEquals($userAgent, $identity->getUserAgent());
        $this->assertEquals([], $identity->getDefaultRequestHeaders());
        $this->assertTrue($identity->areCookiesEnabled());
        $this->assertFalse($identity->isJSEnabled());
        $this->assertTrue($identity->isSendReferer());
    }

    public function testToString()
    {
        $gatewayServer = GatewayServer::localhost();
        $gateway = new Gateway(new EventDispatcher(), $gatewayServer);
        $userAgent = 'Arachne';
        $requestHeaders =  ['Accept-Encoding'=>'utf-8'];
        $areCookiesEnabled = true;
        $isJsEnabled = false;
        $isSendReferer = true;
        $identity = new Identity($gateway, $userAgent, $requestHeaders, $areCookiesEnabled, $isJsEnabled, $isSendReferer);
        $expected = json_encode([
            'Gateway' => (string)$gatewayServer,
            'User Agent' => $userAgent,
            'Default Request Headers' => $requestHeaders,
            'Enable Cookies?' => 'Yes',
            'Enable JavaScript?' => 'No',
            'Send Referer?' => 'Yes'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->assertEquals($expected, (string) $identity);
    }
}
