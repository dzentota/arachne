<?php

namespace Arachne\Filter;
use Arachne\Hash\Hashable;

/**
 * Interface FilterInterface
 * @package Arachne\Filter
 */
interface FilterInterface
{

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed
     */
    public function add(string $filterName, Hashable $resource);

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed
     */
    public function remove(string $filterName, Hashable $resource);

    /**
     * @param string $filterName
     * @param Hashable $resource
     * @return mixed
     */
    public function exists(string $filterName, Hashable $resource) : bool ;

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
