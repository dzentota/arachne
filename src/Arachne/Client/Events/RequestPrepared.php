<?php

namespace Arachne\Client\Events;

use Psr\Http\Message\RequestInterface;
use Arachne\Proxy\SwitchableIdentityProxyInterface;
use Arachne\Event\Event;

class RequestPrepared extends Event
{
    private $request;
    private $config;

    const name = 'client.request_prepared';

    public function __construct(RequestInterface $request, array &$config)
    {
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

}
