<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Domain\Shared\LocalizedText;

final readonly class ProductionPagePayload
{
    public function __construct(
        public LocalizedText $intro = new LocalizedText(),
        public LocalizedText $process = new LocalizedText(),
        public LocalizedText $faq = new LocalizedText(),
    ) {
    }
}
