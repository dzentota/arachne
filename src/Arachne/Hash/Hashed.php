<?php

namespace Arachne\Hash;

use Arachne\Serializable;

class Hashed implements Hashable
{
    private string $value;

    public static function fromString(string $string): Hashable
    {
        $hashed = new self();
        $hashed->value = $string;
        return $hashed;
    }

    public static function fromObject(Serializable $object): Hashable
    {
        $hashed = new self();
        $hashed->value = serialize($object);
        return $hashed;
    }

    public function getHash(): string
    {
        return sha1($this->value);
    }
}