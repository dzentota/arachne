<?php

namespace Arachne\Crawler;

class Value
{
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function trim()
    {
        $this->value = trim($this->value);
        return $this;
    }

    public function replace($from, $to, &$count = null)
    {
        $this->value = str_replace($from, $to, $this->value, $count);
        return $this;
    }

    public function val()
    {
        return $this->value;
    }
}