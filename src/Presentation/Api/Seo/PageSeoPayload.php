<?php

declare(strict_types=1);

namespace App\Presentation\Api\Seo;

use App\Domain\Shared\LocalizedText;

final readonly class PageSeoPayload
{
    public function __construct(
        public LocalizedText $title = new LocalizedText(),
        public LocalizedText $description = new LocalizedText(),
    ) {
    }
}
