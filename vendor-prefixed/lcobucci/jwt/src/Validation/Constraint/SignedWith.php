<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Validation\Constraint;

use MemberPress\Lcobucci\JWT\Signer;
use MemberPress\Lcobucci\JWT\Token;
use MemberPress\Lcobucci\JWT\UnencryptedToken;
use MemberPress\Lcobucci\JWT\Validation\ConstraintViolation;
use MemberPress\Lcobucci\JWT\Validation\SignedWith as SignedWithInterface;

final class SignedWith implements SignedWithInterface
{
    private Signer $signer;
    private Signer\Key $key;

    public function __construct(Signer $signer, Signer\Key $key)
    {
        $this->signer = $signer;
        $this->key    = $key;
    }

    public function assert(Token $token): void
    {
        if (! $token instanceof UnencryptedToken) {
            throw ConstraintViolation::error('You should pass a plain token', $this);
        }

        if ($token->headers()->get('alg') !== $this->signer->algorithmId()) {
            throw ConstraintViolation::error('Token signer mismatch', $this);
        }

        if (! $this->signer->verify($token->signature()->hash(), $token->payload(), $this->key)) {
            throw ConstraintViolation::error('Token signature mismatch', $this);
        }
    }
}
