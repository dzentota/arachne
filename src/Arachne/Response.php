<?php

declare(strict_types=1);

namespace Arachne;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @mixin Crawler
 */
final class Response implements ResponseInterface
{
    private Crawler $crawler;

    public function __construct(private ResponseInterface $response, private RequestInterface $request)
    {
        $this->crawler = new Crawler((string) $response->getBody(), (string) $request->getUri());
    }

    public function __call(string $method, array $args): mixed
    {
        return $this->crawler->{$method}(...$args);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getStatus(): int
    {
        return $this->response->getStatusCode();
    }

    public function getBody()
    {
        return $this->response->getBody();
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion($version): Response
    {
        $this->response = $this->response->withProtocolVersion($version);
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function hasHeader($name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader($name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine($name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader($name, $value): Response
    {
        $this->response = $this->response->withHeader($name, $value);
        return $this;
    }

    public function withAddedHeader($name, $value): Response
    {
        $this->response =  $this->response->withAddedHeader($name, $value);
        return $this;
    }

    public function withoutHeader($name): Response
    {
        $this->response =  $this->response->withoutHeader($name);
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function withStatus($code, $reasonPhrase = ''): Response
    {
        $this->response = $this->response->withStatus($code, $reasonPhrase);
        return $this;
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function withBody(StreamInterface $body): Response
    {
        $this->response = $this->response->withBody($body);
        $this->crawler = new Crawler($body, (string) $this->request->getUri());
        return $this;
    }
}