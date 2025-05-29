<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Signer\Ecdsa;

use MemberPress\Lcobucci\JWT\Signer\UnsafeEcdsa;

use const OPENSSL_ALGO_SHA256;

/** @deprecated Deprecated since v4.2 */
final class UnsafeSha256 extends UnsafeEcdsa
{
    public function algorithmId(): string
    {
        return 'ES256';
    }

    public function algorithm(): int
    {
        return OPENSSL_ALGO_SHA256;
    }

    public function pointLength(): int
    {
        return 64;
    }
}
