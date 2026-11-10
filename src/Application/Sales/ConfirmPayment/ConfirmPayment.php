<?php

declare(strict_types=1);

namespace App\Application\Sales\ConfirmPayment;

final readonly class ConfirmPayment
{
    public function __construct(public string $sessionId)
    {
    }
}
