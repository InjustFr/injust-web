<?php

declare(strict_types=1);

namespace App\Application\Sales\PreviewWithdrawal;

final readonly class WithdrawalPreview
{
    public function __construct(
        public string $reference,
        public string $productType,
        public string $productLabel,
        public int $amountCents,
        public \DateTimeImmutable $paidAt,
        public \DateTimeImmutable $lastDay,
    ) {
    }
}
