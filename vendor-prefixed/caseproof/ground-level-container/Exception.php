<?php

declare(strict_types=1);

namespace MemberPress\GroundLevel\Container;

use MemberPress\Psr\Container\ContainerExceptionInterface;
use Exception as BaseException;

class Exception extends BaseException implements ContainerExceptionInterface
{
}
