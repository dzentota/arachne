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
     * @param HttpResource $item
     * @param int $priority
     */
    public function schedule(HttpResource $item, $priority = FrontierInterface::PRIORITY_NORMAL)
    {
        assert(in_array($priority, [FrontierInterface::PRIORITY_NORMAL, FrontierInterface::PRIORITY_HIGH]),
            sprintf('$priority can be only %s or %s',
                FrontierInterface::PRIORITY_NORMAL,
                FrontierInterface::PRIORITY_HIGH
            )
        );
        switch (true) {
            case $item instanceof \Arachne\Resource:
                $this->populateSingleItem($item, $priority);
                break;
            case $item instanceof \Arachne\BatchResource:
                foreach ($item->getResources() as $resource) {
                    if (!$this->isNewResource($resource)) {
                        $this->getLogger()->notice(
                            sprintf("Resource [%s] %s is already scheduled/visited. Removed from batch",
                                $resource->getType(),
                                $resource->getUrl()
                            )
                        );
                        $item->removeResource($resource);
                    }
                }
                if ($item->count()) {
                    $this->getFrontier()->populate($item, $priority);
                    $this->logger->debug(sprintf('Scheduled batch of %d resources', $item->count()));
                } else {
                    $this->getLogger()->notice("Batch is empty. Skipped");
                }
                break;
            default:
                throw new \InvalidArgumentException(
                    sprintf('Instance of Arachne\HttpResource expected. %s given', gettype($item)));
                break;
        }
    }

    /**
     * @param \Arachne\Resource|Resource $resource
     * @param int $priority
     */
    private function populateSingleItem(
        Resource $resource,
        int $priority
    ) {
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

    /**
     * @param Resource $resource
     * @return bool
     */
    private function isNewResource(Resource $resource): bool
    {
        if ($this->isScheduled($resource)) {
            return false;
        }

        if ($this->isVisited($resource) && !$this->canContainLinksToNewResources($resource)) {
            return false;
        }

        return true;
    }

    /**
     * @param Resource $resource
     * @return bool
     */
    private function canContainLinksToNewResources(Resource $resource): bool
    {
        return (bool)preg_match('~!$~s', $resource->getType());
    }

    /**
     * @param \Arachne\Resource|Resource $resource
     */
    public function markVisited(Resource $resource)
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
     * @param Resource $resource
     * @return bool
     */
    public function isVisited(Resource $resource): bool
    {
        return (bool)$this->getFilter()->exists('visited', $resource);
    }

    /**
     * @param Resource $resource
     */
    public function markScheduled(Resource $resource)
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
     * @param Resource $resource
     * @return bool
     */
    public function isScheduled(Resource $resource): bool
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
}