<?php

declare(strict_types=1);

namespace App\Application\Seo\EditPageSeo;

use App\Domain\Seo\PageKey;
use App\Domain\Shared\LocalizedText;

final readonly class EditPageSeo
{
    public function __construct(
        public PageKey $key,
        public LocalizedText $title,
        public LocalizedText $description,
    ) {
    }
}
