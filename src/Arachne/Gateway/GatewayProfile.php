<?php

namespace Arachne\Gateway;

class GatewayProfile implements GatewayProfileInterface
{

    private $whitList;
    private $blackList;

    public function __construct(array $whiteList = null, array $blackList = null)
    {
        $this->whitList = $whiteList?? ['.*'];
        $this->blackList = $blackList?? [];
    }

    public function getWhiteList(): array
    {
        return $this->whitList;
    }

    public function getBlackList(): array
    {
        return $this->blackList;
    }
}
