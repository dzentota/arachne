<?php

namespace Arachne\Client;

use Arachne\Arachne;
use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Exceptions\NoGatewaysLeftException;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\Gateway\Localhost;
use Arachne\HttpResource;
use Arachne\Identity\Identity;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Promise\settle;

class Guzzle extends Arachne
{
    protected function prepareConfig(Identity $identity): array
    {
        $config['allow_redirects']['referer'] = $identity->isSendReferer();
        $config['headers'] = $identity->getDefaultRequestHeaders();
        $config['headers']['User-Agent'] = $identity->getUserAgent();
        $proxy = $identity->getGateway()->getGatewayServer();
        if (!($proxy instanceof Localhost)) {
            $config['proxy'] = (string)$proxy;
        }
        $config['cookies'] = $identity->areCookiesEnabled() ? new CookieJar() : false;
        return $config;
    }

    /**
     * @param HttpResource[] $resources
     */
    public function process(HttpResource ...$resources)
    {
        $requests = (function () use ($resources) {
            foreach ($resources as $resource) {
                try {
                    $identity = $this->identityRotator->switchIdentityFor($resource->getHttpRequest());
                    $config = $this->prepareConfig($identity);
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