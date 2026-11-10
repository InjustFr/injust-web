<?php

declare(strict_types=1);

namespace App\Application\Lessons;

use App\Domain\Lessons\LessonsPage;
use App\Domain\Shared\LocalizedText;

final readonly class LessonsTextView
{
    private function __construct(
        public LocalizedText $intro,
        public LocalizedText $faq,
    ) {
    }

    public static function of(LessonsPage $page): self
    {
        return new self($page->intro(), $page->faq());
    }
}
