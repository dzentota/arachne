<?php
namespace  Arachne\Frontier\InMemory;

class OrderedPriorityQueue extends \SplPriorityQueue
{
    protected $serial = PHP_INT_MAX;
    /**
     * At this time, the documentation sais "Note: Multiple elements with the same priority will get dequeued
     * in no particular order."
     * @param mixed $value
     * @param mixed $priority
     */
    public function insert($value, $priority)
    {
        parent::insert($value, array($priority, $this->serial--));//To force elements to go in insertion order
    }
}
