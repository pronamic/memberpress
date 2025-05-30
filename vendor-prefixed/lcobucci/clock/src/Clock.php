<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\Clock;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
