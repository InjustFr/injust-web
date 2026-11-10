<?php

declare(strict_types=1);

namespace App\Application\Sales\StartCheckout;

final readonly class CheckoutStarted
{
    public function __construct(
        public string $orderId,
        public string $url,
    ) {
    }
}
