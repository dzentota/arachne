<?php

namespace Arachne\Interval;

use Arachne\Random\RandomizerInterface;
use Arachne\Random\SystemRandomizer;

abstract class BaseRandomInterval
{
    /**
     * @var int
     */
    protected $from;
    /**
     * @var int
     */
    protected $to;
    /**
     * @var int|null
     */
    protected $currentInterval;
    /**
     * @var RandomizerInterface
     */
    protected $randomizer;

    /**
     * Time interval in seconds that is randomly choosen
     * @param int $from
     * @param int $to
     * @param RandomizerInterface $randomizer [optional]. Default: SystemRandomizer.
     */
    function __construct($from, $to, RandomizerInterface $randomizer = null)
    {
        $this->to = $to;
        $this->from = $from;
        $this->randomizer = $randomizer?? new SystemRandomizer();
    }

    /**
     * Returns the current interval. If none is set, a new one is randomly created.
     * @return int
     */
    protected function getCurrentInterval()
    {
        if ($this->currentInterval === null) {
            $this->currentInterval = $this->randomizer->randNum($this->from, $this->to);
        }
        return $this->currentInterval;
    }
}