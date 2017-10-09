<?php

namespace Arachne\Frontier;

use Psr\Log\LoggerInterface;
use Arachne\BatchResource;

/**
 * Class FrontierLogger
 * @package Arachne\Frontier
 */
class FrontierLogger implements FrontierInterface
{

    /**
     * @var FrontierInterface
     */
    private $frontier;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FrontierLogger constructor.
     * @param FrontierInterface $frontier
     * @param LoggerInterface $logger
     */
    public function __construct(FrontierInterface $frontier, LoggerInterface $logger)
    {
        $this->frontier = $frontier;
        $this->logger = $logger;
    }

    /**
     * @param \Serializable $item
     * @param int $priority
     * @return mixed
     */
    public function populate(\Serializable $item, int $priority = self::PRIORITY_NORMAL)
    {
        $this->logger->debug(sprintf('Populating Frontier with %s Resource [%s], %s priority',
            ($item instanceof BatchResource? 'Batch' : 'Single'),
            ($item instanceof BatchResource? $item->count() . ' resources' : $item->getUrl()),
            ($priority === self::PRIORITY_NORMAL ? '"normal"' : '"high"')));
        $this->frontier->populate($item, $priority);
    }

    /**
     * @return \Serializable|null
     */
    public function nextItem()
    {
        $this->logger->debug('Getting next Item from Frontier');
        return $this->frontier->nextItem();
    }

    /**
     * @return mixed
     */
    public function clear()
    {
        $this->logger->debug('Clearing Frontier');
        $this->frontier->clear();
    }
}
