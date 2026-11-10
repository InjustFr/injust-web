<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\WithdrawalRequest;

final readonly class WithdrawalRequestView
{
    private function __construct(
        public string $id,
        public string $orderId,
        public string $orderReference,
        public string $email,
        public ?string $name,
        public ?string $reason,
        public string $locale,
        public \DateTimeImmutable $receivedAt,
        public ?\DateTimeImmutable $acknowledgedAt,
    ) {
    }

    public static function of(WithdrawalRequest $request): self
    {
        return new self(
            $request->id()->toBase32(),
            $request->orderId()->toBase32(),
            $request->orderReference(),
            $request->email(),
            $request->name(),
            $request->reason(),
            $request->locale(),
            $request->receivedAt(),
            $request->acknowledgedAt(),
        );
    }
}
