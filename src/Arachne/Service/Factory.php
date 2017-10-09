<?php

namespace Arachne\Service;

use Arachne\Gateway\GatewayProfile;
use Arachne\Identity\IdentityRotatorInterface;
use Gaufrette\Filesystem;
use Http\Message\RequestFactory;
use Http\Message\ResponseFactory;
use Psr\Log\LoggerInterface;
use Arachne\Client\ClientInterface;
use Arachne\Document\DocumentInterface;
use Arachne\Document\Manager;
use Arachne\Filter\FilterInterface;
use Arachne\Frontier\FrontierInterface;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Scheduler;
use Arachne\Arachne;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Factory
 * @package Arachne\Service
 */
abstract class Factory
{
    /**
     * @var
     */
    private $container;

    /**
     * @return Arachne
     */
    abstract public function scraper(): Arachne;
    /**
     * @return FrontierInterface
     */
    abstract public function frontier(): FrontierInterface;

    /**
     * @return ClientInterface
     */
    abstract public function client(): ClientInterface;

    /**
     * @return DocumentInterface
     */
    abstract public function documentStorage(): DocumentInterface;

    /**
     * @return FilterInterface
     */
    abstract public function filter(): FilterInterface;


    /**
     * @return Manager
     */
    abstract public function documentManager(): Manager;

    /**
     * @return LoggerInterface
     */
    abstract public function logger(): LoggerInterface;

    /**
     * @return RequestFactory
     */
    abstract public function requestFactory(): RequestFactory;

    /**
     * @return ResponseFactory
     */
    abstract public function responseFactory(): ResponseFactory;

    /**
     * @return Filesystem
     */
    abstract public function filesystem() : Filesystem;

    /**
     * @return IdentitiesCollection
     */
    abstract public function identities() : IdentitiesCollection;

    /**
     * @return IdentityRotatorInterface
     */
    abstract public function identityRotator() : IdentityRotatorInterface;

    abstract public function gatewayProfile() : GatewayProfile;

    /**
     * @return Factory
     */
    final public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param Proxy $container
     */
    final public function setContainer(Proxy $container)
    {
        $this->container = $container;
    }

    abstract function scheduler() : Scheduler;

    abstract function eventDispatcher(): EventDispatcherInterface;
}
