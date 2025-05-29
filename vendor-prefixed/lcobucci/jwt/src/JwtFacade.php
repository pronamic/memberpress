<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT;

use Closure;
use DateTimeImmutable;
use MemberPress\Lcobucci\Clock\Clock;
use MemberPress\Lcobucci\Clock\SystemClock;
use MemberPress\Lcobucci\JWT\Encoding\ChainedFormatter;
use MemberPress\Lcobucci\JWT\Encoding\JoseEncoder;
use MemberPress\Lcobucci\JWT\Signer\Key;
use MemberPress\Lcobucci\JWT\Validation\Constraint;
use MemberPress\Lcobucci\JWT\Validation\SignedWith;
use MemberPress\Lcobucci\JWT\Validation\ValidAt;
use MemberPress\Lcobucci\JWT\Validation\Validator;

use function assert;

final class JwtFacade
{
    private Parser $parser;
    private Clock $clock;

    public function __construct(?Parser $parser = null, ?Clock $clock = null)
    {
        $this->parser = $parser ?? new Token\Parser(new JoseEncoder());
        $this->clock  = $clock ?? SystemClock::fromSystemTimezone();
    }

    /** @param Closure(Builder, DateTimeImmutable):Builder $customiseBuilder */
    public function issue(
        Signer $signer,
        Key $signingKey,
        Closure $customiseBuilder
    ): UnencryptedToken {
        $builder = new Token\Builder(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates());

        $now = $this->clock->now();
        $builder
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+5 minutes'));

        return $customiseBuilder($builder, $now)->getToken($signer, $signingKey);
    }

    public function parse(
        string $jwt,
        SignedWith $signedWith,
        ValidAt $validAt,
        Constraint ...$constraints
    ): UnencryptedToken {
        $token = $this->parser->parse($jwt);
        assert($token instanceof UnencryptedToken);

        (new Validator())->assert(
            $token,
            $signedWith,
            $validAt,
            ...$constraints
        );

        return $token;
    }
}
