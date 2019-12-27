<?php

namespace Arachne\Identity;

use Arachne\Gateway\GatewayInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface IdentityRotatorInterface
{
    public function switchIdentityFor(RequestInterface $request): Identity;
    public function getCurrentIdentity(): Identity;
    public function evaluate(GatewayInterface $gateway, ResponseInterface $response): bool;
    public function evaluateResult(Identity $identity, ?ResponseInterface $response);
}
