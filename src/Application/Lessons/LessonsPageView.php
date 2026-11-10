<?php

declare(strict_types=1);

namespace App\Application\Lessons;

use App\Domain\Shared\LocalizedText;

final readonly class LessonsPageView
{
    /** @param list<LessonOfferView> $offers */
    public function __construct(
        public LocalizedText $intro,
        public LocalizedText $faq,
        public array $offers,
    ) {
    }
}
