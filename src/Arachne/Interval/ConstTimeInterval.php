<?php

namespace Arachne\Interval;

/**
 * Class ConstTimeInterval
 * @package Arachne\Interval
 */
class ConstTimeInterval implements TimeIntervalInterface
{

    /**
     * @var int
     */
    private $delay;
    /**
     * @var SystemTimeProvider|TimeProviderInterface
     */
    private $timeProvider;
    /**
     * @var
     */
    private $lastActionTime;

    /**
     * ConstTimeInterval constructor.
     * @param int $delay
     * @param TimeProviderInterface|null $timeProvider
     */
    public function __construct(int $delay, TimeProviderInterface $timeProvider = null)
    {
        $this->delay = $delay;
        $this->timeProvider = $timeProvider?? new SystemTimeProvider();
    }

    /**
     * Checks if sufficient time has passed to satisfy the current time interval.
     * @return bool
     */
    public function isReady()
    {
        if ($this->lastActionTime === null) {
            return true;
        }
        $t = $this->getWaitingTime();
        return ($t <= 0);
    }

    /**
     * Gets the time in microseconds that needs to pass until $this->isReady become true.
     * @return int
     */
    public function getWaitingTime()
    {
        if ($this->lastActionTime === null) {
            return 0;
        }
        $diff = $this->lastActionTime + ($this->delay / 1000000) - $this->timeProvider->getTime();
        return $diff;
    }

    /**
     * Resets the current time interval and set the time of the last action to now
     */
    public function restart()
    {
        $this->lastActionTime = $this->timeProvider->getTime();
    }

    /**
     * Resets the current time interval and set the time of the last action to null.
     * This means that isReady will return true and getWaitingTime will return 0 until
     * $this->restart is called the next time.
     */
    public function reset()
    {
        $this->lastActionTime = null;
    }

}