<?php

declare(strict_types=1);

namespace App\Application\Seo\UploadPageSeoImage;

use App\Domain\Seo\PageKey;

final readonly class UploadPageSeoImage
{
    public function __construct(
        public PageKey $key,
        public string $path,
    ) {
    }
}
