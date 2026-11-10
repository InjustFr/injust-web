<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Domain\Settings\VatMentions;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VatMentionsPayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: VatMentions::MAX_TEXT)]
        public string $consumer = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: VatMentions::MAX_TEXT)]
        public string $euBusiness = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: VatMentions::MAX_TEXT)]
        public string $nonEuBusiness = '',
    ) {
    }
}
