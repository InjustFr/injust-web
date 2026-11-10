<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class StripeRefund
{
    private const string SUCCEEDED = 'succeeded';

    public function __construct(
        public string $id,
        public int $amountCents,
        public string $status,
        public ?string $reason = null,
    ) {
    }

    public function succeeded(): bool
    {
        return self::SUCCEEDED === $this->status;
    }
}
