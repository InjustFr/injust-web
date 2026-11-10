<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Beats\BeatsPage;
use App\Domain\Shared\LocalizedText;

final readonly class BeatsTextView
{
    private function __construct(
        public LocalizedText $intro,
        public LocalizedText $faq,
    ) {
    }

    public static function of(BeatsPage $page): self
    {
        return new self($page->intro(), $page->faq());
    }
}
