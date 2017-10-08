<?php

namespace Arachne;

class Scenario
{
    private $callStack = [];

    public function __call($name, $arguments)
    {
        // TODO: implement validation
        $this->callStack[] = [$name => $arguments];
        return $this;
    }

    public function getCallStack()
    {
        return $this->callStack;
    }
}
