<?php

namespace Arachne\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Identity\Identity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


abstract class GenericClient implements ClientInterface
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * GuzzleClient constructor.
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function sendRequest(RequestInterface $request, array $requestConfig = []): ResponseInterface
    {
        $this->eventDispatcher->dispatch(RequestPrepared::name, new RequestPrepared($request, $requestConfig));
        $response = $this->sendHTTPRequest($request, $requestConfig);
        $this->eventDispatcher->dispatch(ResponseReceived::name, new ResponseReceived($request, $response));
        return $response;
    }

    /**
     * @param $identity
     */
    abstract public function ensureIdentityIsCompatibleWithClient(Identity $identity);

    /**
     * @param array $requestConfig
     * @param $identity
     * @return mixed
     */
    abstract public function prepareConfig(
        array $requestConfig,
        Identity $identity
    ): array;

    abstract protected function sendHTTPRequest(
        RequestInterface $request,
        array $config
    ): ResponseInterface;

}
