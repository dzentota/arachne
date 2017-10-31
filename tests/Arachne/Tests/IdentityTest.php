<?php

namespace Arachne\Tests;

use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\Identity;
use Symfony\Component\EventDispatcher\EventDispatcher;

class IdentityTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $userAgent = 'Test User Agent';
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost());
        $identity = new Identity($gateway, $userAgent);
        $this->assertEquals($gateway, $identity->getGateway());
        $this->assertEquals($userAgent, $identity->getUserAgent());
        $this->assertEquals([], $identity->getDefaultRequestHeaders());
        $this->assertTrue($identity->areCookiesEnabled());
        $this->assertFalse($identity->isJSEnabled());
        $this->assertTrue($identity->isSendReferer());
    }

    public function testSetGet()
    {
        $userAgent = 'Test User Agent';
        $defaultRequestHeaders = ['foo' => 'bar'];
        $enableCookies = false;
        $enableJS = true;
        $isSendReferer = true;
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost());

        $identity = new Identity($gateway, $userAgent, $defaultRequestHeaders, $enableCookies, $enableJS, $isSendReferer);
        $this->assertEquals($userAgent, $identity->getUserAgent());
        $this->assertEquals($defaultRequestHeaders, $identity->getDefaultRequestHeaders());
        $this->assertFalse($identity->areCookiesEnabled());
        $this->assertTrue($identity->isJSEnabled());
        $this->assertTrue($identity->isSendReferer());

        $identity->setDefaultRequestHeaders(['abc' => 'xyz']);
        $this->assertEquals(['abc' => 'xyz'], $identity->getDefaultRequestHeaders());

        $identity->enableCookies();
        $this->assertTrue($identity->areCookiesEnabled());
        $identity->disableCookies();
        $this->assertFalse($identity->areCookiesEnabled());

        $identity->enableJS();
        $this->assertTrue($identity->isJSEnabled());
        $identity->disableJS();
        $this->assertFalse($identity->isJSEnabled());

        $identity->sendReferer();
        $this->assertTrue($identity->isSendReferer());
        $identity->skipReferer();
        $this->assertFalse($identity->isSendReferer());
    }

    public function testToString()
    {
        $userAgent = 'Test User Agent';
        $defaultRequestHeaders = ['foo' => 'bar'];
        $enableCookies = false;
        $enableJS = true;
        $isSendReferer = true;
        $gateway = new Gateway(new EventDispatcher(), GatewayServer::localhost());

        $identity = new Identity($gateway, $userAgent, $defaultRequestHeaders, $enableCookies, $enableJS, $isSendReferer);

        $data = [
            'Gateway' => (string) GatewayServer::localhost(),
            'User Agent' => $userAgent,
            'Default Request Headers' => $defaultRequestHeaders,
            'Enable Cookies?' => 'No',
            'Enable JavaScript?' => 'Yes',
            'Send Referer?' => 'Yes'
        ];
        $expectedString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->assertEquals($expectedString, (string) $identity);
    }
}