<?php

declare(strict_types=1);

namespace App\Presentation\Api\Withdrawal;

use App\Domain\Sales\Order;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class WithdrawalLookupPayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Order::MAX_TYPED_REFERENCE, normalizer: 'trim')]
        public string $reference = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT, normalizer: 'trim')]
        #[Assert\Length(max: Order::MAX_EMAIL, normalizer: 'trim')]
        public string $email = '',
    ) {
    }
}
