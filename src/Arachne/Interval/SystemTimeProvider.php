<?php

namespace Arachne\Interval;


class SystemTimeProvider implements TimeProviderInterface
{

    /**
     * Returns the current time in microseconds. Uses microtime()
     * @return int
     */
    public function getTime()
    {
        return microtime(true);
    }
} 