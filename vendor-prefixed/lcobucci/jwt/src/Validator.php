<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT;

use MemberPress\Lcobucci\JWT\Validation\Constraint;
use MemberPress\Lcobucci\JWT\Validation\NoConstraintsGiven;
use MemberPress\Lcobucci\JWT\Validation\RequiredConstraintsViolated;

interface Validator
{
    /**
     * @throws RequiredConstraintsViolated
     * @throws NoConstraintsGiven
     */
    public function assert(Token $token, Constraint ...$constraints): void;

    /** @throws NoConstraintsGiven */
    public function validate(Token $token, Constraint ...$constraints): bool;
}
