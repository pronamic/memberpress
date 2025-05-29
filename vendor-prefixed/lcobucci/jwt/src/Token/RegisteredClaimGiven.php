<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Token;

use InvalidArgumentException;
use MemberPress\Lcobucci\JWT\Exception;

use function sprintf;

final class RegisteredClaimGiven extends InvalidArgumentException implements Exception
{
    private const DEFAULT_MESSAGE = 'Builder#withClaim() is meant to be used for non-registered claims, '
                                  . 'check the documentation on how to set claim "%s"';

    public static function forClaim(string $name): self
    {
        return new self(sprintf(self::DEFAULT_MESSAGE, $name));
    }
}
