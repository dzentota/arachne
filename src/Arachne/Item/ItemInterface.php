<?php
declare(strict_types=1);

namespace Arachne\Item;

interface ItemInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    public function setId(string $id);

    /**
     * @return string
     */
    public function getType(): string;

    public function validate(): bool;

    public function asArray(): array;
}