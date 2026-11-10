<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class CountryTotal
{
    public function __construct(
        public string $country,
        public string $productType,
        public int $amountCents,
        public int $count,
    ) {
    }
}
