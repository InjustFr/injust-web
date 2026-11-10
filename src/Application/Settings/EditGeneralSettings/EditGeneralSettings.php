<?php

declare(strict_types=1);

namespace App\Application\Settings\EditGeneralSettings;

use App\Domain\Settings\VatMentions;
use App\Domain\Shared\LocalizedText;

final readonly class EditGeneralSettings
{
    public function __construct(
        public string $contactEmail,
        public ?string $bookingUrl,
        public VatMentions $vatMentions,
        public LocalizedText $seoTitle,
        public LocalizedText $seoDescription,
        public bool $searchIndexing,
    ) {
    }
}
