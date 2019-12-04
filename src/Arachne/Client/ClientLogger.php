<?php

namespace Arachne\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ClientLogger implements ClientInterface
{
    /**
     * @var ClientInterface
     */
    private $client;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FilterLogger constructor.
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     */
    public function __construct(ClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function sendRequest(RequestInterface $request, array $config = []): ResponseInterface
    {
        $this->logger->info('Loading resource from URL ' . $request->getUri());
        $this->logger->debug('Request config: ' . (empty($config) ? '<EMPTY>' : implode('; ', $config)));
        $response = $this->client->sendRequest($request, $config);
        $this->logger->debug(sprintf('Got response. Status code: %s, Content-Length: %s',
            $response->getStatusCode(), $response->getHeaderLine('Content-Length')));
        return $response;
    }
}
