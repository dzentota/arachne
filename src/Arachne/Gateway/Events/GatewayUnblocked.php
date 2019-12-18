<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;

class GatewayUnblocked extends GatewayEvent implements EventSummaryInterface
{
    const name = 'gateway.unblocked';

    public function getSummary(): string
    {
        return sprintf('Gateway %s unblocked', (string) $this->getGatewayServer());
    }
}
