<?php

namespace Arachne\Tests\Gateway;

use Arachne\Gateway\Localhost;

class LocalhostTest  extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $localhost = new Localhost();
        $this->assertEquals('http', $localhost->getType());
        $this->assertEquals(80, $localhost->getPort());
        $this->assertEquals('127.0.0.1', $localhost->getIp());
        $this->assertEquals('http://127.0.0.1:80', (string)$localhost);

        $this->assertNull($localhost->getUsername());
        $this->assertNull($localhost->getPassword());
    }
}