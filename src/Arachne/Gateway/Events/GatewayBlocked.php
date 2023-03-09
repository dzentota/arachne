<?php
declare(strict_types=1);

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Event\Event;
use Arachne\Gateway\GatewayServerInterface;

class GatewayBlocked extends Event implements EventSummaryInterface
{
    private GatewayServerInterface $gateway;

    public function __construct(GatewayServerInterface $gatewayServer)
    {
        $this->gateway = $gatewayServer;
    }

    public function getSummary(): string
    {
        return sprintf('Gateway %s blocked', $this->gateway);
    }
}
