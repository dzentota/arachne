<?php

namespace Arachne\Hash;

interface Hashable
{
    public function getHash(): string;
}