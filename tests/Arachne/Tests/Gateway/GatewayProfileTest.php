<?php

namespace Arachne\Tests\Gateway;

use Arachne\Gateway\GatewayProfile;

class GatewayProfileTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $profile = new GatewayProfile();
        $this->assertEquals([], $profile->getBlackList());
        $this->assertEquals(['.*'], $profile->getWhiteList());
    }

    public function testSetGetBlacklist()
    {
        $blackList = ['google\.com'];
        $profile = new GatewayProfile(null, $blackList);
        $this->assertEquals($blackList, $profile->getBlackList());
    }

    public function testSetGetWhiteList()
    {
        $whiteList = ['facebook\.com'];
        $profile = new GatewayProfile($whiteList);
        $this->assertEquals($whiteList, $profile->getWhiteList());
    }
}