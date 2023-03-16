<?php

namespace Arachne;

use Arachne\Frontier\FrontierInterface;
use Arachne\Hash\Hashable;
use Arachne\Item\Item;
use Arachne\Item\ItemInterface;
use Http\Message\RequestFactory;
use Psr\Http\Message\StreamInterface;

/**
 * Class ResultSet
 * @package Phpantom
 */
class ResultSet
{
    private StreamInterface $blob;
    /**
     * @var Hashable[]
     */
    private array $markedAsVisited = [];
    /**
     * @var \Arachne\HttpResource|HttpResource
     */
    private HttpResource $resource;

    /**
     * @var RequestFactory
     */
    private RequestFactory $requestFactory;

    /**
     * @param HttpResource $resource
     * @param RequestFactory $requestFactory
     */
    public function __construct(HttpResource $resource, RequestFactory $requestFactory)
    {
        $this->resource = $resource;
        $this->requestFactory = $requestFactory;
    }

    /**
     * @var HttpResource[]
     */
    private array $newResources = [];

    /**
     * @var HttpResource[]
     */
    private array $relatedResources = [];

    /**
     * @var ItemInterface[]
     */
    private array $items = [];

    /**
     * @var bool
     */
    private bool $isBlob = false;

    /**
     * @param $type
     * @param $url
     * @param ItemInterface|null $item
     * @param string $method
     * @param null $body
     * @param array $headers
     * @param array $meta
     * @return $this
     */
    public function addResource(
        $type,
        $url,
        ItemInterface $item = null,
        string $method = 'GET',
        $body = null,
        array $headers = [],
        array $meta = []
    ) {
        $this->addResourceWithPriority(FrontierInterface::PRIORITY_NORMAL, $type, $url, $item, $method, $body, $headers, $meta);
        return $this;
    }


    /**
     * @param $type
     * @param $url
     * @param ItemInterface|null $item
     * @param string $method
     * @param null $body
     * @param array $headers
     * @param array $meta
     * @return $this
     */
    public function addHighPriorityResource(
        $type,
        $url,
        ItemInterface $item = null,
        string $method = 'GET',
        $body = null,
        array $headers = [],
        array $meta = []
    ) {
        $this->addResourceWithPriority(FrontierInterface::PRIORITY_HIGH, $type, $url, $item, $method, $body, $headers,
            $meta);
        return $this;
    }

    /**
     * @param $priority
     * @param $type
     * @param $url
     * @param null $item
     * @param string $method
     * @param null $body
     * @param array $headers
     */
    protected function addResourceWithPriority(
        $priority,
        $type,
        $url,
        ItemInterface $item = null,
        string $method = 'GET',
        $body = null,
        array $headers = [],
        array $meta = []
    ): static {
        $httpRequest = $this->requestFactory->createRequest($method, $url, $headers, $body ?? null)
            ->withHeader('Referer', $this->resource->getUrl());
        $newResource = new HttpResource($httpRequest, $type);
        if (!empty($meta)) {
            $newResource->addMeta($meta);
        }
        if (empty($item)) {
            $this->newResources[$priority][] = $newResource;
        } else {
            $newResource->addMeta(
                [
                    'related_type' => $item->getType(),
                    'related_id' => $item->getId(),
                    'related_url' => $this->resource->getUrl()
                ]
            );
            $this->relatedResources[$priority][] = $newResource;
        }
        return $this;
    }

    /**
     * @param HttpResource $newResource
     * @param ItemInterface|null $item
     * @param int $priority
     */
    public function addNewResource(
        HttpResource $newResource,
        ItemInterface $item = null,
        int $priority = FrontierInterface::PRIORITY_NORMAL
    ): static {
        if (empty($item)) {
            $this->newResources[$priority][] = $newResource;
        } else {
            $newResource->addMeta(
                [
                    'related_type' => $item->getType(),
                    'related_id' => $item->getId(),
                    'related_url' => $this->resource->getUrl()
                ]
            );
            $this->relatedResources[$priority][] = $newResource;
        }
        return $this;
    }


    /**
     * @param Item $item
     * @return $this
     */
    public function addItem(ItemInterface $item): static
    {
        if ($item->getType() === $this->resource->getMeta('related_type')) {
            $item->setId($this->resource->getMeta('related_id', $item->getId()));
        }
        if ($item->validate()) {
            $this->items[] = $item;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getNewResources(): array
    {
        return $this->newResources;
    }

    /**
     * @return array
     */
    public function getRelatedResources(): array
    {
        return $this->relatedResources;
    }

    /**
     * @return array
     */
    public function getParsedResources(): array
    {
        $normalPriority = array_merge($this->newResources[FrontierInterface::PRIORITY_NORMAL] ?? [],
            $this->relatedResources[FrontierInterface::PRIORITY_NORMAL] ?? []);
        $highPriority = array_merge($this->newResources[FrontierInterface::PRIORITY_HIGH] ?? [],
            $this->relatedResources[FrontierInterface::PRIORITY_HIGH] ?? []);
        return [
            FrontierInterface::PRIORITY_NORMAL => $normalPriority,
            FrontierInterface::PRIORITY_HIGH => $highPriority
        ];
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return bool
     */
    public function isBlob(): bool
    {
        return $this->isBlob;
    }

    public function getBlob(): StreamInterface
    {
        return $this->blob;
    }

    /**
     * @param StreamInterface $stream
     * @return $this
     */
    public function addBlob(StreamInterface $stream): static
    {
        $this->isBlob = true;
        $this->blob = $stream;
        return $this;
    }

    /**
     * @return \Arachne\HttpResource|HttpResource
     */
    public function getResource(): HttpResource
    {
        return $this->resource;
    }

    public function markVisited(Hashable $hashable): void
    {
        $this->markedAsVisited[$hashable->getHash()] = $hashable;
    }

    public function getMarkedAsVisited(): array
    {
        return $this->markedAsVisited;
    }
}