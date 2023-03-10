<?php

namespace Arachne\Identity;

use Arachne\Exceptions\NoGatewaysLeftException;
use Psr\Http\Message\RequestInterface;

class RoundRobinIdentityRotator extends IdentityRotator
{
    private \InfiniteIterator $identitiesList;

    public function __construct(IdentitiesCollection $identitiesCollection, callable $evaluationFunction = null)
    {
        parent::__construct($identitiesCollection, $evaluationFunction);
        $this->identitiesList = new \InfiniteIterator(
            iterator: new class($identitiesCollection->getIterator()) extends \FilterIterator {
                public RequestInterface $currentRequest;
                /**
                 * Check whether the current element of the iterator is acceptable
                 * @link http://php.net/manual/en/filteriterator.accept.php
                 * @return bool true if the current element is acceptable, otherwise false.
                 * @since 5.1.0
                 */
                public function accept(): bool
                {
                    /** @var Identity $currentIdentity */
                    $currentIdentity = parent::current();
                    $gateway = $currentIdentity->getGateway();
                    if (isset($this->currentRequest)) {
                        return $gateway->isUsableFor($this->currentRequest);
                    }
                    return true;
                }
            }
        );
        $this->identitiesList->rewind();
        $this->currentIdentity = $this->identitiesList->current();
    }

    /**
     * @param RequestInterface $request
     * @return Identity
     * @throws NoGatewaysLeftException
     */
    public function switchIdentityFor(RequestInterface $request): Identity
    {
        $this->identitiesList->getInnerIterator()->currentRequest = $request;
        $this->identitiesList->next();
        $this->currentIdentity = $this->identitiesList->current();
        if (null === $this->currentIdentity) {
            throw new NoGatewaysLeftException();
        }
        return $this->currentIdentity;
    }

}