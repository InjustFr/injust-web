<?php

declare(strict_types=1);

namespace App\Application\Sales\StartCheckout;

use App\Domain\Sales\ProductType;

final readonly class StartCheckout
{
    /** @param list<string> $acceptedConsents */
    public function __construct(
        public ProductType $productType,
        public string $productId,
        public string $locale,
        public string $email,
        public array $acceptedConsents,
        public int $cgvVersion,
        public ?string $tierId = null,
        public ?int $licenceVersion = null,
    ) {
    }
}
