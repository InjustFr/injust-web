<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Domain\Music\Platform;

final readonly class HiddenPlatformsPayload
{
    /** @param list<Platform> $platforms */
    public function __construct(
        public array $platforms = [],
    ) {
    }
}
