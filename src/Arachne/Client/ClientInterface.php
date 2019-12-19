<?php

namespace Arachne\Client;

use Arachne\Identity\Identity;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    public function sendRequest(RequestInterface $request, array $config = []) : ResponseInterface;

    public function prepareConfig(
        array $requestConfig,
        Identity $identity
    ): array;

    public function ensureIdentityIsCompatibleWithClient(Identity $identity);
}
