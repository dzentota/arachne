<?php

namespace Arachne\Dumper;

class Csv implements DumperInterface
{
    /**
     * @var false|resource
     */
    private $handler;

    private string $separator;
    private string $enclosure;
    private string $escape;

    public function __construct(string $filename, $separator = ',', $enclosure = '"', $escape = "\\")
    {
        $this->handler = fopen($filename, 'w');
        $this->separator = $separator;
        $this->enclosure = $enclosure;
        $this->escape = $escape;

        if (!$this->handler) {
            throw new \RuntimeException(sprintf('Cannot open CSV file "%s" for writing', $filename));
        }
    }

    public function dump(iterable $data): void
    {
        foreach ($data as $document) {
            $row = is_array($document) ? $document : $document->getData();
            fputcsv($this->handler, $row, $this->separator, $this->enclosure, $this->escape);
        }
    }
}