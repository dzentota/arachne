<?php

namespace Arachne;

use Http\Message\RequestFactory;
use Arachne\Frontier\FrontierInterface;

/**
 * Class ResultSet
 * @package Phpantom
 */
class ResultSet
{
    /**
     * @var \Arachne\Resource|Resource
     */
    private $resource;

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * @param Resource $resource
     * @param RequestFactory $requestFactory
     */
    public function __construct(Resource $resource, RequestFactory $requestFactory)
    {
        $this->resource = $resource;
        $this->requestFactory = $requestFactory;
    }

    /**
     * @var array
     */
    private $newResources = [];

    /**
     * @var array
     */
    private $relatedResources = [];

    /**
     * @var array
     */
    private $items = [];

    /**
     * @var bool
     */
    private $isBlob = false;

    /**
     * @param $type
     * @param $url
     * @param null $item
     * @param string $method
     * @param null $body
     * @param array $headers
     * @return $this
     */
    public function addResource($type, $url, $item = null, $method = 'GET', $body = null, $headers = [])
    {
        $this->addResourceWithPriority(FrontierInterface::PRIORITY_NORMAL, $type, $url, $item, $method, $body,
            $headers);
        return $this;
    }

    /**
     * @param BatchResource $resource
     * @param int $priority
     * @return $this
     */
    public function addBatch(BatchResource $resource, $priority = FrontierInterface::PRIORITY_NORMAL)
    {
        $this->newResources[$priority][] = $resource;
        return $this;
    }

    /**
     * @param $type
     * @param $url
     * @param null $item
     * @param string $method
     * @param null $body
     * @param array $headers
     * @return $this
     */
    public function addHighPriorityResource($type, $url, $item = null, $method = 'GET', $body = null, $headers = [])
    {
        $this->addResourceWithPriority(FrontierInterface::PRIORITY_HIGH, $type, $url, $item, $method, $body, $headers);
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
        $item = null,
        $method = 'GET',
        $body = null,
        $headers = []
    ) {
        $httpRequest = $this->requestFactory->createRequest($method, $url, $headers, $body?? null)
            ->withHeader('Referer', $this->resource->getUrl());
        $newResource = new Resource($httpRequest, $type);
        if (empty($item)) {
            $this->newResources[$priority][] = $newResource;
        } else {
            $newResource->addMeta(
                [
                    'related_type' => $item->type,
                    'related_id' => $item->id,
                    'related_url' => $this->resource->getUrl()
                ]
            );
            $this->relatedResources[$priority][] = $newResource;
        }
    }

    /**
     * @param Resource $newResource
     * @param Item|null $item
     * @param int $priority
     */
    public function addNewResource(Resource $newResource, Item $item = null, $priority = FrontierInterface::PRIORITY_NORMAL)
    {
        if (empty($item)) {
            $this->newResources[$priority][] = $newResource;
        } else {
            $newResource->addMeta(
                [
                    'related_type' => $item->type,
                    'related_id' => $item->id,
                    'related_url' => $this->resource->getUrl()
                ]
            );
            $this->relatedResources[$priority][] = $newResource;
        }
    }


    /**
     * @param Item $item
     * @return $this
     */
    public function addItem(Item $item)
    {
        if ($item->type === $this->resource->getMeta('related_type')) {
            $item->id = $this->resource->getMeta('related_id', $item->id);
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
        $normalPriority = array_merge($this->newResources[FrontierInterface::PRIORITY_NORMAL]??[],
            $this->relatedResources[FrontierInterface::PRIORITY_NORMAL]??[]);
        $highPriority = array_merge($this->newResources[FrontierInterface::PRIORITY_HIGH]??[],
            $this->relatedResources[FrontierInterface::PRIORITY_HIGH]??[]);
        return [
            FrontierInterface::PRIORITY_NORMAL => $normalPriority,
            FrontierInterface::PRIORITY_HIGH => $highPriority
        ];
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return mixed
     */
    public function isBlob(): bool
    {
        return $this->isBlob;
    }

    /**
     * @return $this
     */
    public function markAsBlob()
    {
        $this->isBlob = true;
        return $this;
    }

    /**
     * @return \Arachne\Resource|Resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param int $size
     * @return $this
     */
    public function packInBatch($size = 5)
    {
        $newResources = $this->newResources[FrontierInterface::PRIORITY_NORMAL]?? [];
        $this->newResources[FrontierInterface::PRIORITY_NORMAL] = [];

        $newHighPriorityResources = $this->newResources[FrontierInterface::PRIORITY_HIGH]?? [];
        $this->newResources[FrontierInterface::PRIORITY_HIGH] = [];


        foreach (array_chunk($newResources, $size) as $chunk) {
            $batch = new BatchResource();
            $batch->addResources(...$chunk);
            $this->newResources[FrontierInterface::PRIORITY_NORMAL][] = $batch;
        }

        foreach (array_chunk($newHighPriorityResources, $size) as $chunk) {
            $batch = new BatchResource();
            $batch->addResources(...$chunk);
            $this->newResources[FrontierInterface::PRIORITY_HIGH][] = $batch;
        }

        $relatedResources = $this->relatedResources[FrontierInterface::PRIORITY_NORMAL]?? [];
        $this->relatedResources[FrontierInterface::PRIORITY_NORMAL] = [];

        $relatedHighPriorityResources = $this->relatedResources[FrontierInterface::PRIORITY_HIGH]?? [];
        $this->relatedResources[FrontierInterface::PRIORITY_HIGH] = [];


        foreach (array_chunk($relatedResources, $size) as $chunk) {
            $batch = new BatchResource();
            $batch->addResources(...$chunk);
            $this->relatedResources[FrontierInterface::PRIORITY_NORMAL][] = $batch;
        }

        foreach (array_chunk($relatedHighPriorityResources, $size) as $chunk) {
            $batch = new BatchResource();
            $batch->addResources(...$chunk);
            $this->relatedResources[FrontierInterface::PRIORITY_HIGH][] = $batch;
        }
        return $this;
    }
}
