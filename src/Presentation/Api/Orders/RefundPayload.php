<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RefundPayload
{
    public function __construct(
        #[Assert\Positive]
        public int $amountCents,
        #[Assert\Length(max: 500)]
        public string $reason = '',
    ) {
    }
}
