<?php

declare(strict_types=1);

namespace Arachne\PostProcessor;

class Output implements PostProcessorInterface
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

    public function processData(array $data): array
    {
        $string = var_export($data, true);
        fwrite($this->stream, $string);
        return $data;
    }
}