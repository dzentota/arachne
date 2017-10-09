<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Event\Event;
use Arachne\Gateway\GatewayServerInterface;

class GatewaySucceeded extends Event implements EventSummaryInterface
{
    private $gateway;
    const name = 'gateway.succeeded';

    public function __construct(GatewayServerInterface $gatewayServer)
    {
        $this->gateway = $gatewayServer;
    }

    public function getSummary(): string
    {
        return sprintf('Gateway %s succeeded', (string) $this->gateway);
    }
}
