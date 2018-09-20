<?php

namespace Arachne\Client;

use Arachne\Identity\IdentityRotatorInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Exceptions\HttpRequestException;
use Arachne\Identity\Identity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


abstract class GenericClient implements ClientInterface
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    private $identityRotator;

    /**
     * GuzzleClient constructor.
     * @param EventDispatcherInterface $eventDispatcher
     * @param IdentityRotatorInterface $identityRotator
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, IdentityRotatorInterface $identityRotator)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->identityRotator = $identityRotator;
    }

    public function sendRequest(RequestInterface $request, array $requestConfig = []): ResponseInterface
    {
        $this->identityRotator->switchIdentityFor($request);
        $identity = $this->identityRotator->getCurrentIdentity();
            $this->ensureIdentityIsCompatibleWithClient($identity);
        $config = $this->prepareConfig($requestConfig, $identity);
        try {
            $this->eventDispatcher->dispatch(RequestPrepared::name, new RequestPrepared($request, $config));
            $response = $this->sendHTTPRequest($request, $config);
            $this->eventDispatcher->dispatch(ResponseReceived::name, new ResponseReceived($request, $response));
            $this->identityRotator->evaluateResult($response);
        } catch (\Exception $exception) {
            $this->getIdentityRotator()->evaluateResult(null);
            throw new HttpRequestException('Failed to send HTTP Request', 0, $exception);
        }
        return $response;
    }

    public function getIdentityRotator(): IdentityRotatorInterface
    {
        return $this->identityRotator;
    }
    /**
     * @param $identity
     */
    abstract protected function ensureIdentityIsCompatibleWithClient(Identity $identity);

    /**
     * @param array $requestConfig
     * @param $identity
     * @return mixed
     */
    abstract protected function prepareConfig(
        array $requestConfig,
        Identity $identity
    ): array;

    abstract protected function sendHTTPRequest(
        RequestInterface $request,
        array $config
    ): ResponseInterface;

}
