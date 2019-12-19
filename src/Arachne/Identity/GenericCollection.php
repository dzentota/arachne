<?php

namespace Arachne\Identity;

/**
 * Class GenericCollection
 * @package Arachne
 */
abstract class GenericCollection implements Collection, \IteratorAggregate, \Countable
{
    /**
     * @return array
     */
    public abstract function toArray(): array;

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * @param $offset
     * @param null $length
     * @return static
     */
    public function slice($offset, $length = null)
    {
        $slice = array_slice($this->toArray(), $offset, $length);
        return new static(...$slice);
    }
}
