<?php

namespace Arachne\Service;

class Container
{
    private $serviceProxy;

    public function __construct(Proxy $proxy)
    {
        $this->serviceProxy = $proxy;
    }

    /**
     * Hack. Return type specified as ServiceFactory for autocomplete
     * @return Factory
     */
    public function get()
    {
        return $this->serviceProxy;
    }

    /**
     * @param Proxy|null $proxy
     * @return $this
     */
    public static function create(Proxy $proxy = null)
    {
        return (new static($proxy? : new Proxy(new GenericFactory())));
    }
}
