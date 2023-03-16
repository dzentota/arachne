<?php

declare(strict_types=1);

namespace Arachne\PostProcessor;

class Csv implements PostProcessorInterface
{
    private $handler;

    public function __construct(
        string $filename,
        private readonly string $separator = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = "\\"
    ) {
        $this->handler = fopen($filename, 'w');

        if (!$this->handler) {
            throw new \RuntimeException(sprintf('Cannot open CSV file "%s" for writing', $filename));
        }
    }

    public function processData(array $data): array
    {
        fputcsv($this->handler, $data, $this->separator, $this->enclosure, $this->escape);
        return $data;
    }
}