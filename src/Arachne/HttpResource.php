<?php

namespace Arachne;

use Arachne\Hash\Hashable;
use Arachne\MessageFactory\GuzzleMessageFactory;
use Http\Message\RequestFactory;
use JetBrains\PhpStorm\ArrayShape;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Request\Serializer;
use Psr\Http\Message\RequestInterface;

class HttpResource implements Hashable, Serializable
{
    /**
     * @var array
     */
    private array $meta = [];
    /**
     * @var RequestInterface
     */
    private $httpRequest;

    public function __construct(RequestInterface $httpRequest, private string $type)
    {
        $request = $httpRequest->withRequestTarget((string)$httpRequest->getUri());
        $this->httpRequest = $request;
    }

    public static function fromUrl(string $url, string $type = 'default'): HttpResource
    {
        return new static(new Request( $url, 'GET'), $type);
    }

    public function __sleep(): array
    {
        return [];
    }

    public function getUrl(): string
    {
        return (string)$this->httpRequest->getUri();
    }

    /**
     * @return RequestInterface
     */
    public function getHttpRequest(): RequestInterface
    {
        return $this->httpRequest;
    }

    /**
     * @return mixed
     */
    public function getType(): string
    {
        return $this->type;
    }


    /**
     * @return string
     */
    public function getHash(): string
    {
        return sha1(Serializer::toString($this->getHttpRequest()));
    }

    /**
     * @param array $meta
     * @return $this
     */
    public function setMeta(array $meta = [])
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * @param array $meta
     * @return $this
     */
    public function addMeta(array $meta = [])
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * @param null $var
     * @param null $default
     * @return mixed
     */
    public function getMeta($var = null, $default = null)
    {
        if (null === $var) {
            return $this->meta;
        }
        return $this->meta[$var] ?? $default;
    }

    /**
     * Proxy
     * @param $method
     * @param array $params
     * @return mixed
     */
    public function __call($method, $params = [])
    {
        $result = call_user_func_array([$this->httpRequest, $method], $params);
        if (str_starts_with($method, 'with')) {
            $this->httpRequest = $result;
            return $this;
        }
        return $result;
    }

    #[ArrayShape(['meta' => "array", 'type' => "string", 'httpRequest' => "string"])] public function __serialize(
    ): array
    {
        return [
            'meta' => $this->meta,
            'type' => $this->type,
            'httpRequest' => Serializer::toString($this->getHttpRequest())
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->meta = $data['meta'];
        $this->type = $data['type'];
        //@todo make it more flexible
        $this->httpRequest = Serializer::fromString($data['httpRequest']);
    }
}