<?php

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;

class GatewayFailed extends GatewayEvent implements EventSummaryInterface
{
    const name = 'gateway.failed';

    public function getSummary(): string
    {
        return sprintf('Gateway %s failed', (string) $this->getGatewayServer());
    }
}
