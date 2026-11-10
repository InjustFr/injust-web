<?php

declare(strict_types=1);

namespace App\Application\Lessons\EditLessonsPage;

use App\Domain\Shared\LocalizedText;

final readonly class EditLessonsPage
{
    public function __construct(
        public LocalizedText $intro,
        public LocalizedText $faq,
    ) {
    }
}
