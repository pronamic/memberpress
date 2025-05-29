<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Validation\Constraint;

use DateInterval;
use MemberPress\Lcobucci\Clock\Clock;
use MemberPress\Lcobucci\JWT\Token;
use MemberPress\Lcobucci\JWT\Validation\Constraint;

/** @deprecated Use \Lcobucci\JWT\Validation\Constraint\LooseValidAt */
final class ValidAt implements Constraint
{
    private LooseValidAt $constraint;

    public function __construct(Clock $clock, ?DateInterval $leeway = null)
    {
        $this->constraint = new LooseValidAt($clock, $leeway);
    }

    public function assert(Token $token): void
    {
        $this->constraint->assert($token);
    }
}
