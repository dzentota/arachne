<?php

namespace Arachne\Frontier;

/**
 * Class InMemory
 * @package Arachne\Frontier
 */
class InMemory implements FrontierInterface
{

    private $queue;

    /**
     * @param \SplPriorityQueue $queue
     */
    public function __construct(\SplPriorityQueue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @param \Serializable $item
     * @param int $priority
     * @return mixed
     */
    public function populate(\Serializable $item, int $priority = self::PRIORITY_NORMAL)
    {
        $this->queue->insert($item, $priority);
    }

    /**
     * @return \Serializable
     */
    public function nextItem()
    {
        if ($this->queue->valid()) {
            return $this->queue->extract();
        }
        return null;
    }

    /**
     */
    public function clear()
    {
        $class = get_class($this->queue);
        $this->queue = new $class;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->queue->count();
    }
}
