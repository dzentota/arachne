<?php

namespace Arachne\Client\Events;

use Psr\Http\Message\RequestInterface;
use Arachne\Event\Event;

class RequestPrepared extends Event
{
    const name = 'client.request_prepared';

    public function __construct(private readonly RequestInterface $request, private readonly array $config)
    {
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
