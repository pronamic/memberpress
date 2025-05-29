<?php
declare(strict_types=1);

namespace MemberPress\Lcobucci\JWT\Encoding;

use DateTimeImmutable;
use MemberPress\Lcobucci\JWT\ClaimsFormatter;
use MemberPress\Lcobucci\JWT\Token\RegisteredClaims;

use function array_key_exists;

final class MicrosecondBasedDateConversion implements ClaimsFormatter
{
    /** @inheritdoc */
    public function formatClaims(array $claims): array
    {
        foreach (RegisteredClaims::DATE_CLAIMS as $claim) {
            if (! array_key_exists($claim, $claims)) {
                continue;
            }

            $claims[$claim] = $this->convertDate($claims[$claim]);
        }

        return $claims;
    }

    /** @return int|float */
    private function convertDate(DateTimeImmutable $date)
    {
        if ($date->format('u') === '000000') {
            return (int) $date->format('U');
        }

        return (float) $date->format('U.u');
    }
}
