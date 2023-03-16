<?php

declare(strict_types=1);

namespace Arachne\Processor;

use Arachne\ResultSet;

class Output implements ProcessorInterface
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

    public function processResultSet(ResultSet $resultSet): ResultSet
    {
        foreach ($resultSet->getItems() as $item) {
            $string = var_export($item->asArray(), true);
            fwrite($this->stream, $string);
        }
        return $resultSet;
    }
}