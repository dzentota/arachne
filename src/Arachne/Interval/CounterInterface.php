<?php

namespace Arachne\Interval;

interface CounterInterface
{
    /**
     * Increments the counter by 1 and returns the current counter value (after incrementing)
     * @return int
     */
    public function incrementCounter();

    /**
     * @return int
     */
    public function getCounter();

}
