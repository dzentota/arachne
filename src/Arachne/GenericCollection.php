<?php

namespace Arachne;

/**
 * Class GenericCollection
 * @package Arachne
 */
abstract class GenericCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var
     */
    protected $values = [];

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->values);
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
    public function count()
    {
        return count($this->values);
    }

    /**
     * @param $offset
     * @param null $length
     * @return static
     */
    public function slice($offset, $length = null)
    {
        $slice = array_slice($this->values, $offset, $length);
        return new static(...$slice);
    }
}
