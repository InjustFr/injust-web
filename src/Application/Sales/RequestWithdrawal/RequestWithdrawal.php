<?php

declare(strict_types=1);

namespace App\Application\Sales\RequestWithdrawal;

final readonly class RequestWithdrawal
{
    public function __construct(
        public string $reference,
        public string $email,
        public ?string $name,
        public ?string $reason,
        public string $locale,
    ) {
    }
}
