<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Encoding;

use JsonException;
use MemberPress\Lcobucci\JWT\Exception;
use RuntimeException;

final class CannotEncodeContent extends RuntimeException implements Exception
{
    public static function jsonIssues(JsonException $previous): self
    {
        return new self('Error while encoding to JSON', 0, $previous);
    }
}
