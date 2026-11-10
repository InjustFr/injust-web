<?php

declare(strict_types=1);

namespace App\Application\Sales\PreviewWithdrawal;

final readonly class PreviewWithdrawal
{
    public function __construct(
        public string $reference,
        public string $email,
    ) {
    }
}
