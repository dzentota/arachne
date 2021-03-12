<?php

namespace Arachne\Hash;

class Hashed implements Hashable
{
    private $value;

    public static function fromString(string $string): Hashable
    {
        $hashed = new self();
        $hashed->value = $string;
        return $hashed;
    }

    public static function fromObject(\Serializable $object): Hashable
    {
        $hashed = new self();
        $hashed->value = (string) $object;
        return $hashed;
    }

    public function getHash(): string
    {
        return sha1($this->value);
    }
}