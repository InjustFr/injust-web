<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Domain\Shared\Price;

final readonly class PriceFormat
{
    public function format(int $cents, string $locale): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        if (0 === $cents % 100) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
        }

        return (string) $formatter->formatCurrency($cents / 100, Price::CURRENCY);
    }
}
