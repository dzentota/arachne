<?php

namespace Arachne\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    public function sendRequest(RequestInterface $request, array $config = []) : ResponseInterface;
}
