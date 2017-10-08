<?php

namespace Arachne\Gateway;

interface GatewayProfileInterface
{
    public function getWhiteList(): array;

    public function getBlackList(): array;
}
