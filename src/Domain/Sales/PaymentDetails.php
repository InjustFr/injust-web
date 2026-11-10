<?php

declare(strict_types=1);

namespace App\Domain\Sales;

final readonly class PaymentDetails
{
    public function __construct(
        public string $sessionId,
        public string $paymentIntentId,
        public int $amountCents,
        public string $currency,
        public Customer $customer,
        public \DateTimeImmutable $paidAt,
    ) {
    }
}
