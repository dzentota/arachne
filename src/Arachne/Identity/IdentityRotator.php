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
                    if ($response->getStatusCode() === 429 || strlen((string)$response->getBody() === 0)) {
                        $gateway->block();
                    }
                    return false;
                }
                return true;
            };
    }


    abstract public function switchIdentityFor(RequestInterface $request): Identity;

    public function getCurrentIdentity(): Identity
    {
        return $this->currentIdentity;
    }

    /**
     * @param GatewayInterface $gateway
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
    public function evaluateResult(Identity $identity, ?ResponseInterface $response)
    {
        $gateway = $identity->getGateway();
        $gateway->requested(); // increase request count
        if ($response) {
            if ($this->evaluate($gateway, $response)) {
                $gateway->succeeded();
            } else {
                $gateway->failed();
            }
        } else {
            $gateway->failed();
        }
    }

}
