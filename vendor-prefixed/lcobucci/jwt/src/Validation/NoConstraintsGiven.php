<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Validation;

use MemberPress\Lcobucci\JWT\Exception;
use RuntimeException;

final class NoConstraintsGiven extends RuntimeException implements Exception
{
}
