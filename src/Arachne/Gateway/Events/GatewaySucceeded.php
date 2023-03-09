<?php
declare(strict_types=1);

namespace Arachne\Gateway\Events;

use Arachne\Event\EventSummaryInterface;

class GatewaySucceeded extends GatewayEvent implements EventSummaryInterface
{
    public function getSummary(): string
    {
        return sprintf('Gateway %s succeeded', $this->getGatewayServer());
    }
}
