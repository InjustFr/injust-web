<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Domain\Seo\PageSeo;
use App\Domain\Shared\LocalizedText;

final readonly class PageSeoView
{
    private function __construct(
        public string $key,
        public LocalizedText $title,
        public LocalizedText $description,
        public ?string $image,
    ) {
    }

    public static function of(PageSeo $page): self
    {
        return new self($page->key()->value, $page->title(), $page->description(), $page->image());
    }
}
