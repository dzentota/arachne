<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Gateway\GatewayServer;
use Arachne\Event\Event;

class GatewayUnblocked extends Event implements EventSummaryInterface
{
    private $gateway;
    const name = 'gateway.unblocked';

    public function __construct(GatewayServer $gatewayServer)
    {
        $this->gateway = $gatewayServer;
    }

    public function getSummary(): string
    {
        return sprintf('Gateway %s unblocked', (string) $this->gateway);
    }
}
