<?php

namespace Arachne\Engine;

use Arachne\Engine;
use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Exceptions\NoGatewaysLeftException;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\HttpResource;
use Arachne\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;

class Async extends Engine
{
    /**
     * @param HttpResource[] $resources
     */
    public function process(HttpResource ...$resources)
    {
        $requests = (function () use ($resources) {
            foreach ($resources as $resource) {
                try {
                    $identity = $this->identityRotator->switchIdentityFor($resource->getHttpRequest());
                    $config = $this->client->prepareConfig($identity);
                    $request = $resource->getHttpRequest();
                    $this->eventDispatcher->dispatch(new RequestPrepared($request, $config));
                    yield $this->client->sendAsync($request, $config)
                        ->then(
                            function (ResponseInterface $httpResponse) use ($resource, $identity) {
                                $response = new Response($httpResponse, $resource->getHttpRequest());
                                $this->eventDispatcher->dispatch(new ResponseReceived($resource->getHttpRequest(), $response));
                                $this->identityRotator->evaluateResult($identity, $response);
                                $this->scheduler->markVisited($resource);
                                if ($response->getStatusCode() === 200) {
                                    try {
                                        $this->handleHttpSuccess($resource, $response);
                                    } catch (ParsingResponseException $exception) {
                                        $this->handleException($resource, $response, $exception);
                                    }
                                } else {
                                    $this->handleHttpFail($resource, $response);
                                }
                                $this->handleAnyway($resource, $response);
                            },
                            function (RequestException $reason) use ($resource, $identity) {
                                if (null !== $reason->getResponse()) {
                                    $this->eventDispatcher->dispatch(new ResponseReceived($resource->getHttpRequest(),
                                        $reason->getResponse()));
                                }
                                try {
                                    $this->identityRotator->evaluateResult($identity, null);
                                } catch (\Exception $exception) {
                                    $this->handleException($resource, $reason->getResponse(), $exception);
                                }
                                $this->handleException($resource, $reason->getResponse(), $reason);
                                $this->handleAnyway($resource, $reason->getResponse());
                            }
                        );
                } catch (NoGatewaysLeftException $exception) {
                    $this->handleException($resource, null, $exception);
                    $this->shutdown();
                }
            }
        })();

        Utils::settle($requests)->wait();
    }
}