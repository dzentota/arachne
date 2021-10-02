<?php
namespace Arachne\Dumper;

interface DumperInterface
{
    public function dump(iterable $data): void;
}