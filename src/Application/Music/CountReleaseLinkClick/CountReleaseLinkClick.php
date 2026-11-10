<?php

declare(strict_types=1);

namespace App\Application\Music\CountReleaseLinkClick;

use App\Domain\Music\Platform;

final readonly class CountReleaseLinkClick
{
    public function __construct(
        public string $slug,
        public Platform $platform,
    ) {
    }
}
