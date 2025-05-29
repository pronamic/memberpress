<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Signer\Hmac;

use MemberPress\Lcobucci\JWT\Signer\Hmac;

final class Sha384 extends Hmac
{
    public function algorithmId(): string
    {
        return 'HS384';
    }

    public function algorithm(): string
    {
        return 'sha384';
    }

    public function minimumBitsLengthForKey(): int
    {
        return 384;
    }
}
