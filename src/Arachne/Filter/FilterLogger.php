<?php

namespace Arachne\Filter;
use Arachne\Hash\Hashable;

use Psr\Log\LoggerInterface;

/**
 * Class FilterLogger
 * @package Arachne\Filter
 */
class FilterLogger implements FilterInterface
{

    /**
     * @var FilterInterface
     */
    private $filter;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FilterLogger constructor.
     * @param FilterInterface $filter
     * @param LoggerInterface $logger
     */
    public function __construct(FilterInterface $filter, LoggerInterface $logger)
    {
        $this->filter = $filter;
        $this->logger = $logger;
    }

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed
     */
    public function add(string $filterName, Hashable $resource)
    {
        $this->logger->debug(sprintf('Adding new filter [%s][%s]', $filterName, $resource->getHash()));
        $this->filter->add($filterName, $resource);
    }

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed
     */
    public function remove(string $filterName, Hashable $resource)
    {
        $this->logger->debug(sprintf('Removing filter [%s][%s]', $filterName, $resource->getHash()));
        $this->filter->remove($filterName, $resource);
    }

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed
     */
    public function exists(string $filterName, Hashable $resource) : bool
    {
        $this->logger->debug(sprintf('Checking if filter [%s][%s] exists', $filterName, $resource->getHash()));
        return $this->filter->exists($filterName, $resource);
    }

    /**
     * @param string $filterName
     */
    public function clear(string $filterName)
    {
        $this->logger->debug(sprintf('Clearing filter [%s]', $filterName));
        $this->filter->clear($filterName);
    }

    /**
     * @param string $filterName
     * @return int
     */
    public function count(string $filterName): int
    {
        $this->logger->debug(sprintf('Getting size of filter [%s]', $filterName));
        return $this->filter->count($filterName);
    }
}
