<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;

class GatewayRequested extends GatewayEvent implements EventSummaryInterface
{
    const name = 'gateway.requested';

    public function getSummary(): string
    {
        return sprintf('Gateway %s requested', (string) $this->getGatewayServer());
    }
}
