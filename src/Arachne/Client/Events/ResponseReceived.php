<?php

namespace Arachne\Client\Events;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Arachne\Event\Event;

class ResponseReceived extends Event
{
    public function __construct(private readonly RequestInterface $request, private readonly ResponseInterface $response)
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
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

}
