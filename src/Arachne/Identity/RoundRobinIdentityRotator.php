<?php

namespace Arachne\Identity;

use Arachne\Exceptions\NoGatewaysLeftException;
use Psr\Http\Message\RequestInterface;

class RoundRobinIdentityRotator extends IdentityRotator
{
    private $identitiesList;
    private $currentRequest;

    public function __construct(IdentitiesCollection $identitiesCollection, callable $evaluationFunction = null)
    {
        parent::__construct($identitiesCollection, $evaluationFunction);
        $this->identitiesList = new \InfiniteIterator(
            new class($identitiesCollection->getIterator()) extends \FilterIterator {
                public $currentRequest;
                /**
                 * Check whether the current element of the iterator is acceptable
                 * @link http://php.net/manual/en/filteriterator.accept.php
                 * @return bool true if the current element is acceptable, otherwise false.
                 * @since 5.1.0
                 */
                public function accept()
                {
                    /** @var Identity $currentIdentity */
                    $currentIdentity = parent::current();
                    $gateway = $currentIdentity->getGateway();
                    return isset($this->currentRequest)? $gateway->isUsableFor($this->currentRequest) : true;
                }
            }
        );
        $this->identitiesList->rewind();
    }

    public function switchIdentityFor(RequestInterface $request)
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