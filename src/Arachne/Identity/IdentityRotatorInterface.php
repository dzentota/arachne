<?php

namespace Arachne\Identity;

use Psr\Http\Message\RequestInterface;

interface IdentityRotatorInterface
{
    public function switchIdentityFor(RequestInterface $request);
    public function getCurrentIdentity(): Identity;
}
