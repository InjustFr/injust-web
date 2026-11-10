<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class Totals
{
    public const int EU_DIGITAL_THRESHOLD_CENTS = 10_000_00;

    /** @param list<CountryTotal> $byCountryAndType */
    public function __construct(
        public array $byCountryAndType,
        public int $euConsumerDigitalCrossBorderCents,
        public int $euConsumerOnlineLessonsCents,
    ) {
    }
}
