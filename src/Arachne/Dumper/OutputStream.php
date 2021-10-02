<?php

namespace Arachne\Dumper;

class OutputStream implements DumperInterface
{
    private $stream;
    public const OUT = 1;
    public const ERROR = 2;

    public function __construct(int $stream = self::ERROR)
    {
        assert(in_array($stream, [self::OUT, self::ERROR]), sprintf('%d is not a valid output', $stream));
        if ($stream === self::OUT) {
            $this->stream = STDOUT;
        } else {
            $this->stream = STDERR;
        }
    }

    public function dump(iterable $data): void
    {
        foreach ($data as $document) {
            $string = print_r(is_array($document)? $document : $document->getData(), true);
            fwrite($this->stream, $string);
        }
    }
}