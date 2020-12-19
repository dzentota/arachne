<?php

namespace Arachne\Engine;

use Arachne\Engine;
use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Exceptions\NoGatewaysLeftException;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\HttpResource;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Promise\settle;

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
                    $this->logger->info('Loading resource from URL ' . $request->getUri());
                    $this->logger->debug('Request config: ' . (empty($config) ? '<EMPTY>' : var_export(
                            array_map(function ($param) {
                                return ($param instanceof CookieJar) ? true : $param;
                            }, $config), true)));
                    $this->eventDispatcher->dispatch(new RequestPrepared($request, $config));
                    yield $this->client->sendAsync($request, $config)
                        ->then(
                            function (ResponseInterface $response) use ($resource, $identity) {
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
                                    $this->eventDispatcher->dispatch(new ResponseReceived($resource->getHttpRequest(), $reason->getResponse()));
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

        settle($requests)->wait();
    }
}