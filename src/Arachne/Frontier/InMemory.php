<?php

namespace Arachne\Frontier;

use Arachne\Serializable;
use Arachne\ShutdownAware;

/**
 * Class InMemory
 * @package Arachne\Frontier
 */
class InMemory implements FrontierInterface, ShutdownAware
{
    private $queue;

    /**
     * @var string
     */
    private string $storage;

    /**
     * @param \SplPriorityQueue $queue
     */
    public function __construct(\SplPriorityQueue $queue, ?string $storage = null)
    {
        $this->queue = $queue;
        $this->storage = $storage?? sys_get_temp_dir();
        if (file_exists($this->storage . '/arachne_frontier.php')) {
            $frontier = require $this->storage . '/arachne_frontier.php';
            array_map(function (string $item) {
                $this->populate(unserialize($item));
            }, $frontier);
        }
    }

    /**
     * @param Serializable $item
     * @param int $priority
     * @return mixed
     */
    public function populate(Serializable $item, int $priority = self::PRIORITY_NORMAL)
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
        if (file_exists($this->storage . '/arachne_frontier.php')) {
            @unlink($this->storage . '/arachne_frontier.php');
        }
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

    public function onShutdown()
    {
        if (!file_exists($this->storage)) {
            mkdir($this->storage);
        }
        $frontier = [];
        while ($item = $this->nextItem()) {
            $frontier[] = serialize($item);
        }
        $data = '<?php return ' . var_export($frontier, true) . ';';
        file_put_contents($this->storage . '/arachne_frontier.php', $data);
    }
}
