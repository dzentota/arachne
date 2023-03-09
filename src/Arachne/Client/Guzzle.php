<?php

namespace Arachne\Client;

use Arachne\Gateway\Localhost;
use Arachne\Identity\Identity;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Guzzle implements \Arachne\Client\ClientInterface
{

    public function __construct(protected ClientInterface $httpClient)
    {
    }

    /**
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    /**
     * @param ClientInterface $httpClient
     * @return $this
     */
    public function setHttpClient(ClientInterface $httpClient): Guzzle
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @param $identity
     * @return array
     */
    public function prepareConfig(?Identity $identity = null): array
    {
        if ($identity === null) {
            return $this->getDefaultConfig();
        }
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

    protected function getDefaultConfig(): array
    {
        $config['allow_redirects']['referer'] = true;
        $config['cookies'] = new CookieJar();
        return $config;
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->getHttpClient()->send($request, $options);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->getHttpClient()->sendAsync($request, $options);
    }
}