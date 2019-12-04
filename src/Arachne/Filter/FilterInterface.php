<?php

namespace Arachne\Filter;

use Arachne\HttpResource;

/**
 * Interface FilterInterface
 * @package Arachne\Filter
 */
interface FilterInterface
{

    /**
     * @param string $filterName
     * @param HttpResource $resource
     * @return mixed
     */
    public function add(string $filterName, HttpResource $resource);

    /**
     * @param string $filterName
     * @param \Arachne\HttpResource|HttpResource $resource
     * @return mixed
     */
    public function remove(string $filterName, HttpResource $resource);

    /**
     * @param string $filterName
     * @param \Arachne\HttpResource|HttpResource $resource
     * @return mixed
     */
    public function exists(string $filterName, HttpResource $resource) : bool ;

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
