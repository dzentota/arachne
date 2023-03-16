<?php

declare(strict_types=1);

namespace Arachne\Processor;

use Arachne\ResultSet;

class Callback implements ProcessorInterface
{
    private $callback;
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function processResultSet(ResultSet $resultSet): ResultSet
    {
        $callback = $this->callback;
        return $callback($resultSet);
    }
}