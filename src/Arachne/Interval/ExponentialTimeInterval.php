<?php

namespace Arachne\Interval;

class ExponentialTimeInterval implements TimeIntervalInterface, CounterInterface
{
    /**
     * @var int|null
     */
    private $lastActionTime;
    /**
     * @var TimeProviderInterface
     */
    private $timeProvider;

    private $currentInterval;

    /**
     * @var int
     */
    private $counter;

    /**
     * Time interval in seconds that is randomly choosen
     * @param TimeProviderInterface $timeProvider [optional]. Default: SystemTimeProvider.
     */
    function __construct(TimeProviderInterface $timeProvider = null)
    {
        if ($timeProvider === null) {
            $timeProvider = new SystemTimeProvider();
        }
        $this->timeProvider = $timeProvider;
        $this->counter = 0;
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
        $diff = $this->lastActionTime - ($this->timeProvider->getTime() - $this->getCurrentInterval());
        return $diff;
    }

    /**
     * Resets the current time interval and set the time of the last action to now
     */
    public function restart()
    {
        $this->lastActionTime = $this->timeProvider->getTime();
        $this->currentInterval = null;
        $this->counter = 0;
    }

    /**
     * Resets the current time interval and set the time of the last action to null.
     * This means that isReady will return true and getWaitingTime will return 0 until
     * $this->restart is called the next time.
     */
    public function reset()
    {
        $this->lastActionTime = null;
        $this->currentInterval = null;
    }

    protected function getCurrentInterval()
    {
        if ($this->currentInterval === null) {
            $this->counter++;
            $this->currentInterval = (int) pow(2, $this->counter - 1);
        }
        return $this->currentInterval;
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
}
