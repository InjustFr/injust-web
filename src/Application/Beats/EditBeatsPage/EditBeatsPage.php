<?php

declare(strict_types=1);

namespace App\Application\Beats\EditBeatsPage;

use App\Domain\Shared\LocalizedText;

final readonly class EditBeatsPage
{
    public function __construct(
        public LocalizedText $intro,
        public LocalizedText $faq,
    ) {
    }
}
