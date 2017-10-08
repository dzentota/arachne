<?php

namespace Arachne\Filter;

use Arachne\Resource;

/**
 * Interface FilterInterface
 * @package Arachne\Filter
 */
interface FilterInterface
{

    /**
     * @param string $filterName
     * @param Resource $resource
     * @return mixed
     */
    public function add(string $filterName, Resource $resource);

    /**
     * @param string $filterName
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function remove(string $filterName, Resource $resource);

    /**
     * @param string $filterName
     * @param \Arachne\Resource|Resource $resource
     * @return mixed
     */
    public function exists(string $filterName, Resource $resource) : bool ;

    /**
     * @param string $filterName
     */
    public function clear(string $filterName);

    /**
     * @param string $filterName
     * @return int
     */
    public function count(string $filterName) : int;
}
