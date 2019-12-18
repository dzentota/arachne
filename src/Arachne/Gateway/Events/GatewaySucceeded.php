<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;

class GatewaySucceeded extends GatewayEvent implements EventSummaryInterface
{
    const name = 'gateway.succeeded';

    public function getSummary(): string
    {
        return sprintf('Gateway %s succeeded', (string) $this->getGatewayServer());
    }
}
