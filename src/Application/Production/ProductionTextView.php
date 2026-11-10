<?php

declare(strict_types=1);

namespace App\Application\Production;

use App\Domain\Production\ProductionPage;
use App\Domain\Shared\LocalizedText;

final readonly class ProductionTextView
{
    private function __construct(
        public LocalizedText $intro,
        public LocalizedText $process,
        public LocalizedText $faq,
    ) {
    }

    public static function of(ProductionPage $page): self
    {
        return new self($page->intro(), $page->process(), $page->faq());
    }
}
