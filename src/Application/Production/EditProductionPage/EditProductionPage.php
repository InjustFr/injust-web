<?php

declare(strict_types=1);

namespace App\Application\Production\EditProductionPage;

use App\Domain\Shared\LocalizedText;

final readonly class EditProductionPage
{
    public function __construct(
        public LocalizedText $intro,
        public LocalizedText $process,
        public LocalizedText $faq,
    ) {
    }
}
