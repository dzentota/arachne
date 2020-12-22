<?php

namespace Arachne\Engine;

use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Engine;
use Arachne\Exceptions\NoGatewaysLeftException;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\HttpResource;
use GuzzleHttp\Cookie\CookieJar;

class Basic extends Engine
{
    public function process(HttpResource ...$resources)
    {
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
                $response = $this->client->send($request, $config);
                $this->eventDispatcher->dispatch(new ResponseReceived($resource->getHttpRequest(),
                    $response));
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
            } catch (NoGatewaysLeftException $exception) {
                $this->handleException($resource, null, $exception);
                $this->shutdown();
            } catch (\Exception $exception) {
                if (isset($response)) {
                    $this->eventDispatcher->dispatch(new ResponseReceived($resource->getHttpRequest(), $response));
                }
                try {
                    $this->identityRotator->evaluateResult($identity, null);
                } catch (\Exception $exception) {
                    $this->handleException($resource, $response, $exception);
                }
                $this->handleException($resource, $response, $exception);
                $this->handleAnyway($resource, $response);
            }

        }
    }
}