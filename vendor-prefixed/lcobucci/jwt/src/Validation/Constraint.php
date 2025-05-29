<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Validation;

use MemberPress\Lcobucci\JWT\Token;

interface Constraint
{
    /** @throws ConstraintViolation */
    public function assert(Token $token): void;
}
