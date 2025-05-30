<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Validation\Constraint;

use MemberPress\Lcobucci\JWT\Token;
use MemberPress\Lcobucci\JWT\Validation\Constraint;
use MemberPress\Lcobucci\JWT\Validation\ConstraintViolation;

final class IssuedBy implements Constraint
{
    /** @var string[] */
    private array $issuers;

    public function __construct(string ...$issuers)
    {
        $this->issuers = $issuers;
    }

    public function assert(Token $token): void
    {
        if (! $token->hasBeenIssuedBy(...$this->issuers)) {
            throw ConstraintViolation::error(
                'The token was not issued by the given issuers',
                $this
            );
        }
    }
}
