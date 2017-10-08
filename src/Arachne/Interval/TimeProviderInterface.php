<?php namespace Arachne\Interval;

interface TimeProviderInterface
{
    /**
     * Returns the current time in seconds.
     * @return int
     */
    public function getTime();
}