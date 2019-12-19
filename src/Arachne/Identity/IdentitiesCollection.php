<?php

namespace Arachne\Identity;

class IdentitiesCollection extends GenericCollection
{
    /**
     * @var
     */
    protected $values = [];

    public function __construct(Identity ...$identities)
    {
        $this->values = $identities;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function add(Identity ...$identities)
    {
        $this->values = array_merge($this->toArray(), $identities);
    }

}
