<?php

namespace Arachne\Interval;

class NullCounter implements CounterIntervalInterface
{
    /**
     * Returns true
     * @return bool
     */
    public function isReady()
    {
        return true;
    }

    /**
     * Has no effect.
     */
    public function incrementCounter()
    {
        // does nothing
    }

    /**
     * Has no effect.
     */
    public function restart()
    {
        // does nothing
    }

    /**
     * @return int
     */
    public function getCounter()
    {
        return 0;
    }
}
