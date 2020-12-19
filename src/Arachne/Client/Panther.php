<?php

namespace Arachne\Client;

use Arachne\Identity\Identity;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Panther implements ClientInterface
{
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        // TODO: Implement send() method.
    }

    public function sendAsync(RequestInterface $request, array $options = [])
    {
        throw new \LogicException('Not supported');
    }

    public function prepareConfig(?Identity $identity = null): array
    {
        return [];
    }
}