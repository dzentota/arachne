<?php

namespace Arachne\Frontier;

use Arachne\Serializable;

/**
 * Interface FrontierInterface
 * @package Arachne
 */
interface FrontierInterface
{
    /**
     * Normal priority
     */
    const PRIORITY_NORMAL = 1;
    /**
     * High priority
     */
    const PRIORITY_HIGH = 2;

    /**
     * @param Serializable $item
     * @param int $priority
     * @return mixed
     */
    public function populate(Serializable $item, int $priority = self::PRIORITY_NORMAL);

    /**
     * @return \Serializable|null
     */
    public function nextItem();

    /**
     * @return mixed
     */
    public function clear();

}
