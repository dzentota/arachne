<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Event\Event;
use Arachne\Gateway\GatewayServerInterface;

class GatewayBlocked extends Event implements EventSummaryInterface
{
    private $gateway;
    const name = 'gateway.blocked';

    public function __construct(GatewayServerInterface $gatewayServer)
    {
        $this->gateway = $gatewayServer;
    }

    public function getSummary(): string
    {
        return sprintf('Gateway %s blocked', (string) $this->gateway);
    }
}
