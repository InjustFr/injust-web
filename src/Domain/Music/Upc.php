<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Music\Exception\InvalidUpc;

final class Upc
{
    public const int MAX = 13;

    public static function orNull(?string $value): ?string
    {
        $upc = trim($value ?? '');
        if ('' === $upc) {
            return null;
        }

        return ctype_digit($upc) && \in_array(\strlen($upc), [12, 13], true) && self::checks($upc) ? $upc : throw new InvalidUpc($upc);
    }

    private static function checks(string $upc): bool
    {
        $sum = 0;
        foreach (str_split(strrev(substr($upc, 0, -1))) as $index => $digit) {
            $sum += (int) $digit * (0 === $index % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) substr($upc, -1);
    }
}
