<?php

declare(strict_types=1);

namespace App\Application\Sales\RecordRefund;

use App\Application\Sales\StripeRefund;

final readonly class RecordRefund
{
    /** @param list<StripeRefund> $refunds */
    public function __construct(
        public string $paymentIntentId,
        public array $refunds = [],
    ) {
    }
}
