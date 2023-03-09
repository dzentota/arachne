<?php

namespace Arachne\Crawler;

class NullCrawler extends GenericCrawler
{
    public function attr($attribute)
    {
        return null;
    }

    public function text()
    {
        return null;
    }

    public function html()
    {
        return null;
    }

    public function nodeName()
    {
        return null;
    }

    public function links()
    {
        return [];
    }

    public function extract($attributes)
    {
        return [];
    }

    public function __call($name, $arguments)
    {
        return $this;
    }

    public function __toString()
    {
        return '';
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count(): int
    {
        return 0;
    }
}