<?php namespace Arachne\Interval;

interface TimeIntervalInterface extends IntervalInterface
{
    /**
     * Gets the time in seconds that needs to pass until $this->isReady become true.
     * @return int
     */
    public function getWaitingTime();

    public function reset();
}
