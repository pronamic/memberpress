<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Signer\Rsa;

use MemberPress\Lcobucci\JWT\Signer\UnsafeRsa;

use const OPENSSL_ALGO_SHA384;

/** @deprecated Deprecated since v4.2 */
final class UnsafeSha384 extends UnsafeRsa
{
    public function algorithmId(): string
    {
        return 'RS384';
    }

    public function algorithm(): int
    {
        return OPENSSL_ALGO_SHA384;
    }
}
