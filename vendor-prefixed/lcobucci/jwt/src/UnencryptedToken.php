<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT;

use MemberPress\Lcobucci\JWT\Token\DataSet;
use MemberPress\Lcobucci\JWT\Token\Signature;

interface UnencryptedToken extends Token
{
    /**
     * Returns the token claims
     */
    public function claims(): DataSet;

    /**
     * Returns the token signature
     */
    public function signature(): Signature;

    /**
     * Returns the token payload
     */
    public function payload(): string;
}
