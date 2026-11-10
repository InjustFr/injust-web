<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Music\LinkSource;
use App\Domain\Music\Platform;

final readonly class PlatformLinkPayload
{
    public function __construct(
        public Platform $platform = Platform::Other,
        public string $url = '',
        public LinkSource $source = LinkSource::Manual,
    ) {
    }
}
