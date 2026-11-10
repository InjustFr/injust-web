<?php

declare(strict_types=1);

namespace App\Application\Settings\UploadSiteImage;

use App\Domain\Settings\SiteImage;

final readonly class UploadSiteImage
{
    public function __construct(
        public SiteImage $slot,
        public string $path,
    ) {
    }
}
