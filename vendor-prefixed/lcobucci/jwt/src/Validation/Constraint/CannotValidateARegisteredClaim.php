<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Validation\Constraint;

use InvalidArgumentException;
use MemberPress\Lcobucci\JWT\Exception;

final class CannotValidateARegisteredClaim extends InvalidArgumentException implements Exception
{
    public static function create(string $claim): self
    {
        return new self(
            'The claim "' . $claim . '" is a registered claim, another constraint must be used to validate its value'
        );
    }
}
