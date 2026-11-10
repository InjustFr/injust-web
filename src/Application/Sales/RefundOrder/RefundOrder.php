<?php

declare(strict_types=1);

namespace App\Application\Sales\RefundOrder;

use Symfony\Component\Uid\Ulid;

final readonly class RefundOrder
{
    public function __construct(
        public Ulid $orderId,
        public int $amountCents,
        public string $reason,
    ) {
    }
}
