<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Event\Event;
use Arachne\Gateway\GatewayServerInterface;

abstract class GatewayEvent extends Event implements EventSummaryInterface
{
    private $gateway;

    public function __construct(GatewayServerInterface $gatewayServer)
    {
        $this->gateway = $gatewayServer;
    }

    abstract public function getSummary(): string;

    public function getGatewayServer(): GatewayServerInterface
    {
        return $this->gateway;
    }
}
