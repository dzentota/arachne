<?php

declare(strict_types=1);

namespace Arachne\PostProcessor;

class Callback implements PostProcessorInterface
{
    private $callback;
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function processData(array $data): array
    {
        $callback = $this->callback;
        return $callback($data);
    }
}