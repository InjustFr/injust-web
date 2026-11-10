<?php

declare(strict_types=1);

namespace App\Presentation\Api\Withdrawal;

use App\Domain\Sales\Order;
use App\Domain\Sales\WithdrawalRequest;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class WithdrawalPayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Order::MAX_TYPED_REFERENCE, normalizer: 'trim')]
        public string $reference = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT, normalizer: 'trim')]
        #[Assert\Length(max: Order::MAX_EMAIL, normalizer: 'trim')]
        public string $email = '',
        #[Assert\Length(max: WithdrawalRequest::MAX_NAME, normalizer: 'trim')]
        #[Assert\Regex('/^[^\x00-\x1F\x7F]*$/u')]
        public ?string $name = null,
        #[Assert\Length(max: WithdrawalRequest::MAX_REASON, normalizer: 'trim')]
        #[Assert\Regex('/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u')]
        public ?string $reason = null,
        #[Assert\Choice(choices: Order::LOCALES)]
        public string $locale = '',
    ) {
    }
}
