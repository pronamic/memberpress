<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Validation\Constraint;

use MemberPress\Lcobucci\JWT\Token;
use MemberPress\Lcobucci\JWT\Validation\Constraint;
use MemberPress\Lcobucci\JWT\Validation\ConstraintViolation;

final class IdentifiedBy implements Constraint
{
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function assert(Token $token): void
    {
        if (! $token->isIdentifiedBy($this->id)) {
            throw ConstraintViolation::error(
                'The token is not identified with the expected ID',
                $this
            );
        }
    }
}
