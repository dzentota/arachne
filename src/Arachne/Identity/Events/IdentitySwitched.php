<?php

namespace Arachne\Identity\Events;

use Arachne\Event\EventSummaryInterface;
use Arachne\Identity\Identity;
use Arachne\Event\Event;

class IdentitySwitched extends Event implements EventSummaryInterface
{
    private $identity;

    const name = 'identity.switched';

    public function __construct(Identity $identity)
    {
        $this->identity = $identity;
    }

    public function getSummary(): string
    {
        return sprintf('Switched Identity to %s', (string) $this->identity);
    }
}
