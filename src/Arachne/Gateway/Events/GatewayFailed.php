<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Gateway\GatewayServer;
use Arachne\Event\Event;

class GatewayFailed extends Event implements EventSummaryInterface
{
    private $gateway;
    const name = 'gateway.failed';

    public function __construct(GatewayServer $gatewayServer)
    {
        $this->gateway = $gatewayServer;
    }

    public function getSummary(): string
    {
        return sprintf('Gateway %s failed', (string) $this->gateway);
    }
}
