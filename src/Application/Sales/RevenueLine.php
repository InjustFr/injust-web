<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class RevenueLine
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $invoiceNumber,
        public string $reference,
        public string $customerName,
        public string $country,
        public string $productType,
        public int $amountCents,
        public string $paymentMethod,
        public string $label,
    ) {
    }
}
