<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Signer;

use MemberPress\Lcobucci\JWT\Signer\Ecdsa\MultibyteStringConverter;
use MemberPress\Lcobucci\JWT\Signer\Ecdsa\SignatureConverter;

use const OPENSSL_KEYTYPE_EC;

/** @deprecated Deprecated since v4.2 */
abstract class UnsafeEcdsa extends OpenSSL
{
    private SignatureConverter $converter;

    public function __construct(SignatureConverter $converter)
    {
        $this->converter = $converter;
    }

    public static function create(): UnsafeEcdsa
    {
        return new static(new MultibyteStringConverter());  // @phpstan-ignore-line
    }

    final public function sign(string $payload, Key $key): string
    {
        return $this->converter->fromAsn1(
            $this->createSignature($key->contents(), $key->passphrase(), $payload),
            $this->pointLength()
        );
    }

    final public function verify(string $expected, string $payload, Key $key): bool
    {
        return $this->verifySignature(
            $this->converter->toAsn1($expected, $this->pointLength()),
            $payload,
            $key->contents()
        );
    }

    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    final protected function guardAgainstIncompatibleKey(int $type, int $lengthInBits): void
    {
        if ($type !== OPENSSL_KEYTYPE_EC) {
            throw InvalidKeyProvided::incompatibleKeyType(
                self::KEY_TYPE_MAP[OPENSSL_KEYTYPE_EC],
                self::KEY_TYPE_MAP[$type],
            );
        }
    }

    /**
     * Returns the length of each point in the signature, so that we can calculate and verify R and S points properly
     *
     * @internal
     */
    abstract public function pointLength(): int;
}
