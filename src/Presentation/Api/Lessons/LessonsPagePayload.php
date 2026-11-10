<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Domain\Shared\LocalizedText;

final readonly class LessonsPagePayload
{
    public function __construct(
        public LocalizedText $intro = new LocalizedText(),
        public LocalizedText $faq = new LocalizedText(),
    ) {
    }
}
