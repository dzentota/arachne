<?php

namespace Arachne\Gateway;

class Localhost implements GatewayServerInterface
{

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'http';
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return '127.0.0.1';
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return 80;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return 'http://127.0.0.1:80';
    }
}
