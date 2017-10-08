<?php

namespace Arachne\Gateway;

interface GatewayServerInterface
{
    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getIp() : string;

    /**
     * @return int
     */
    public function getPort() : int;

    /**
     * @return mixed
     */
    public function getUsername();

    /**
     * @return mixed
     */
    public function getPassword();

    /**
     * @return mixed
     */
    public function __toString();

}