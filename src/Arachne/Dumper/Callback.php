<?php

namespace Arachne\Dumper;

class Callback implements DumperInterface
{
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function dump(iterable $data): void
    {
        foreach ($data as $document) {
            call_user_func($this->callback, is_array($document) ? $document : $document->getData());
        }
    }
}
