<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Domain\Settings\Settings;
use App\Domain\Shared\LocalizedText;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GeneralSettingsPayload
{
    public function __construct(
        #[Assert\Email]
        #[Assert\Length(max: Settings::MAX_TEXT)]
        public string $contactEmail = '',
        #[Assert\Length(max: Settings::MAX_TEXT)]
        public ?string $bookingUrl = null,
        #[Assert\Valid]
        public VatMentionsPayload $vatMentions = new VatMentionsPayload(),
        public LocalizedText $seoTitle = new LocalizedText(),
        public LocalizedText $seoDescription = new LocalizedText(),
        public bool $searchIndexing = false,
    ) {
    }
}
