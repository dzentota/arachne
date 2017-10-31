<?php

namespace Arachne\Identity;

use Arachne\GenericCollection;

class IdentitiesCollection extends GenericCollection
{
    public function __construct(Identity ...$identities)
    {
        $this->values = $identities;
    }

    public function add(Identity ...$identities)
    {
        $this->values = array_merge($this->values, $identities);
    }
}
