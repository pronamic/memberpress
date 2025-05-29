<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Signer\Hmac;

use MemberPress\Lcobucci\JWT\Signer\Hmac;

final class Sha512 extends Hmac
{
    public function algorithmId(): string
    {
        return 'HS512';
    }

    public function algorithm(): string
    {
        return 'sha512';
    }

    public function minimumBitsLengthForKey(): int
    {
        return 512;
    }
}
