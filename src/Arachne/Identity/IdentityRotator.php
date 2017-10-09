<?php

namespace Arachne\Identity;

use Arachne\Gateway\GatewayInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class IdentityRotator implements IdentityRotatorInterface
{
    /**
     * @var IdentitiesCollection
     */
    private $identities;

    private $evaluationFunction;

    protected $currentIdentity;

    public function __construct(
        IdentitiesCollection $identitiesCollection,
        callable $evaluationFunction = null
    ) {
        $this->identities = $identitiesCollection;
        $this->evaluationFunction = $evaluationFunction?? function (
                GatewayInterface $gateway,
                ResponseInterface $response
            ) {
                if ($response->getStatusCode() !== 200) {
                    $gateway->block();
                    return false;
                }
                return true;
            };
    }


    abstract public function switchIdentityFor(RequestInterface $request);

    public function getCurrentIdentity(): Identity
    {
        return $this->currentIdentity;
    }

    /**
     * @param ResponseInterface $response
     * @return bool
     */
    public function evaluate(GatewayInterface $gateway, ResponseInterface $response): bool
    {
        $f = $this->evaluationFunction;
        return $f($gateway, $response);
    }


    /**
     * @param ResponseInterface $response
     */
    public function evaluateResult(ResponseInterface $response)
    {
        $currentIdentity = $this->getCurrentIdentity();
        $gateway = $currentIdentity->getGateway();
        $gateway->requested(); // increase request count
        if ($this->evaluate($gateway, $response)) {
            $gateway->succeeded();
        } else {
            $gateway->failed();
        }
    }

}
