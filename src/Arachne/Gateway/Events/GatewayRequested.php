<?php
declare(strict_types=1);

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;

class GatewayRequested extends GatewayEvent implements EventSummaryInterface
{
    public function getSummary(): string
    {
        return sprintf('Gateway %s requested', (string) $this->getGatewayServer());
    }
}
