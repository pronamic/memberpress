<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Token;

final class Signature
{
    private string $hash;
    private string $encoded;

    public function __construct(string $hash, string $encoded)
    {
        $this->hash    = $hash;
        $this->encoded = $encoded;
    }

    /** @deprecated Deprecated since v4.3 */
    public static function fromEmptyData(): self
    {
        return new self('', '');
    }

    public function hash(): string
    {
        return $this->hash;
    }

    /**
     * Returns the encoded version of the signature
     */
    public function toString(): string
    {
        return $this->encoded;
    }
}
