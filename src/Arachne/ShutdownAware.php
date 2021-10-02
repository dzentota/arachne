<?php

namespace Arachne;

interface ShutdownAware
{
    public function onShutdown();
}