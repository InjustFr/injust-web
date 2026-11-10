<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class CheckoutRequest
{
    public function __construct(
        public string $orderId,
        public string $reference,
        public string $label,
        public int $amountCents,
        public string $currency,
        public string $locale,
        public ?string $email,
        public string $successUrl,
        public string $cancelUrl,
    ) {
    }
}
