<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Shared\Price;

final readonly class Purchasable
{
    public function __construct(
        public ProductType $type,
        public string $id,
        public string $label,
        public Price $price,
        public ?string $format = null,
        public ?LicensedBeat $beat = null,
    ) {
    }
}
