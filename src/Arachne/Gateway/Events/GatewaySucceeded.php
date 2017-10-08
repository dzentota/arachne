<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Gateway\GatewayServer;
use Arachne\Event\Event;

class GatewaySucceeded extends Event implements EventSummaryInterface
{
    private $gateway;
    const name = 'gateway.succeeded';

    public function __construct(GatewayServer $gatewayServer)
    {
        $this->gateway = $gatewayServer;
    }

    public function getSummary(): string
    {
        return sprintf('Gateway %s succeeded', (string) $this->gateway);
    }
}
