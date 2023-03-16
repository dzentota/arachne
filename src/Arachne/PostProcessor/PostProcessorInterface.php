<?php
declare(strict_types=1);

namespace Arachne\PostProcessor;

interface PostProcessorInterface
{
    public function processData(array $data): array;
}