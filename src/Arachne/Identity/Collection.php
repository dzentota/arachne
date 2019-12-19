<?php

namespace Arachne\Identity;

interface Collection
{

    public function toArray(): array;

    /**
     * @param Identity ...$identities
     * @return mixed
     */
    public function add(Identity ...$identities);

    /**
     * @param $offset
     * @param null $length
     * @return static
     */
    public function slice($offset, $length = null);
}
