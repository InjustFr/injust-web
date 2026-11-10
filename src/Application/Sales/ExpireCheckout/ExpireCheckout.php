<?php

declare(strict_types=1);

namespace App\Application\Sales\ExpireCheckout;

final readonly class ExpireCheckout
{
    public function __construct(public string $sessionId)
    {
    }
}
