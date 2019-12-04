<?php

namespace Arachne;

use Psr\Log\LoggerInterface;
use Arachne\Filter\FilterInterface;
use Arachne\Frontier\FrontierInterface;

class Scheduler
{
    private $frontier;
    private $filter;
    private $logger;

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return FrontierInterface
     */
    public function getFrontier(): FrontierInterface
    {
        return $this->frontier;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter(): FilterInterface
    {
        return $this->filter;
    }

    /**
     * Scheduler constructor.
     * @param FrontierInterface $frontier
     * @param FilterInterface $filter
     * @param LoggerInterface $logger
     */
    public function __construct(FrontierInterface $frontier, FilterInterface $filter, LoggerInterface $logger)
    {
        $this->frontier = $frontier;
        $this->filter = $filter;
        $this->logger = $logger;
    }

    /**
     * @param HttpResource $resource
     * @param int $priority
     */
    public function scheduleNewResource(HttpResource $resource, $priority = FrontierInterface::PRIORITY_NORMAL)
    {
        assert(in_array($priority, [FrontierInterface::PRIORITY_NORMAL, FrontierInterface::PRIORITY_HIGH]),
            sprintf('$priority can be only %s or %s',
                FrontierInterface::PRIORITY_NORMAL,
                FrontierInterface::PRIORITY_HIGH
            )
        );
        if ($this->isScheduled($resource)) {
            $this->getLogger()->notice(
                sprintf("Resource [%s] %s is already scheduled",
                    $resource->getType(),
                    $resource->getUrl()
                )
            );
            return;
        }

        if ($this->isVisited($resource) && !$this->canContainLinksToNewResources($resource)) {
            $this->getLogger()->notice(
                sprintf("Resource [%s] %s is already visited",
                    $resource->getType(),
                    $resource->getUrl()
                )
            );
            return;
        }
        $this->getFrontier()->populate($resource, $priority);
        $this->markScheduled($resource);
    }

    public function schedule(HttpResource $resource, $priority = FrontierInterface::PRIORITY_NORMAL)
    {
        assert(in_array($priority, [FrontierInterface::PRIORITY_NORMAL, FrontierInterface::PRIORITY_HIGH]),
            sprintf('$priority can be only %s or %s',
                FrontierInterface::PRIORITY_NORMAL,
                FrontierInterface::PRIORITY_HIGH
            )
        );
        $this->getFrontier()->populate($resource, $priority);
        $this->markScheduled($resource);
    }

    /**
     * @param \Arachne\HttpResource|HttpResource $resource
     */
    public function markVisited(HttpResource $resource)
    {
        $this->getFilter()->add('visited', $resource);
        $this->getFilter()->remove('scheduled', $resource);
        $this->getLogger()->debug(
            sprintf('Marked Resource [%s] %s as visited',
                $resource->getType(),
                $resource->getUrl()
            )
        );
    }

    /**
     * @param HttpResource $resource
     * @return bool
     */
    public function isVisited(HttpResource $resource): bool
    {
        return (bool)$this->getFilter()->exists('visited', $resource);
    }

    /**
     * @param HttpResource $resource
     */
    public function markScheduled(HttpResource $resource)
    {
        $this->getFilter()->add('scheduled', $resource);
        $this->getLogger()->debug(
            sprintf('Scheduled Resource [%s] %s for crawling',
                $resource->getType(),
                $resource->getUrl()
            )
        );
    }

    /**
     * @param HttpResource $resource
     * @return bool
     */
    public function isScheduled(HttpResource $resource): bool
    {
        return (bool)$this->getFilter()->exists('scheduled', $resource);
    }

    public function nextItem()
    {
        return $this->getFrontier()->nextItem();
    }

    public function clear()
    {
        $this->getFrontier()->clear();
        $this->getFilter()->clear('scheduled');
        $this->getFilter()->clear('visited');//???
    }

    /**
     * @param HttpResource $resource
     * @return bool
     */
    private function canContainLinksToNewResources(HttpResource $resource): bool
    {
        return (bool)preg_match('~!$~s', $resource->getType());
    }

}