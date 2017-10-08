<?php

namespace Arachne\Client\Events;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Arachne\Event\Event;

class ResponseReceived extends Event
{
    private $request;
    private $response;

    const name = 'client.response_received';

    public function __construct(RequestInterface $request, ResponseInterface $response)
    {
        $this->request = $request;
        $this->response = $response;
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
