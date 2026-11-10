<?php

declare(strict_types=1);

namespace App\Application\Sales\RefundPayment;

final readonly class RefundPayment
{
    public function __construct(public string $paymentIntentId)
    {
    }
}
