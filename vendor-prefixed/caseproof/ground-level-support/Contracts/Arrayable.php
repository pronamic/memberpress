<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Support\Contracts;

interface Arrayable
{
    /**
     * Retrieves the instance as an array.
     *
     * @return array
     */
    public function toArray(): array;
}
