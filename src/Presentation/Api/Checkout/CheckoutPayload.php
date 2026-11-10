<?php

declare(strict_types=1);

namespace App\Presentation\Api\Checkout;

use App\Domain\Sales\Order;
use App\Domain\Sales\ProductType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckoutPayload
{
    /** @param list<string> $consents */
    public function __construct(
        public ProductType $productType,
        public string $productId = '',
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT, normalizer: 'trim')]
        #[Assert\Length(max: Order::MAX_EMAIL, normalizer: 'trim')]
        public string $email = '',
        #[Assert\Choice(choices: Order::LOCALES)]
        public string $locale = '',
        public array $consents = [],
        public int $cgvVersion = 0,
    ) {
    }
}
