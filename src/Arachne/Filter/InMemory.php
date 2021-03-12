<?php

namespace Arachne\Filter;

use Arachne\Hash\Hashable;

/**
 * Class InMemory
 * @package Arachne\Filter
 */
class InMemory implements FilterInterface
{
    /**
     * @var array
     */
    private $hash = [];

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed|void
     */
    public function add(string $filterName, Hashable $resource)
    {
        $this->hash[$filterName][$resource->getHash()] = true;
    }

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed|void
     */
    public function remove(string $filterName, Hashable $resource)
    {
        unset($this->hash[$filterName][$resource->getHash()]);
    }

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return bool
     */
    public function exists(string $filterName, Hashable $resource) : bool
    {
        return isset($this->hash[$filterName][$resource->getHash()]);
    }

    /**
     * @param string $filterName
     */
    public function clear(string $filterName)
    {
        unset($this->hash[$filterName]);
    }

    /**
     * @param string $filterName
     * @return mixed
     */
    public function count(string $filterName) : int
    {
        if (!isset($this->hash[$filterName])) {
            return 0;
        }
        return count($this->hash[$filterName]);
    }
}
