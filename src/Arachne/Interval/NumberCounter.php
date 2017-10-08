<?php

namespace Arachne\Interval;

class NumberCounter implements CounterIntervalInterface
{
    /**
     * @var int
     */
    private $counter;

    private $number;

    public function __construct($number)
    {
        $this->number = $number;
        $this->counter = 0;
    }

    /**
     * Returns true if the current random interval is lower or equal the counter value
     * @return bool
     */
    public function isReady()
    {
        if ($this->counter >= $this->number) {
            return true;
        }
        return false;
    }

    /**
     * Increments the counter by 1 and returns the current counter value (after incrementing)
     * @return int
     */
    public function incrementCounter()
    {
        $this->counter++;
        return $this->counter;
    }

    /**
     * @return int
     */
    public function getCounter()
    {
        return $this->counter;
    }


    /**
     * Restarts the interval
     * @return void
     */
    public function restart()
    {
        $this->counter = 0;
    }
}
