<?php

declare(strict_types=1);

namespace App\Application\Music\HideReleasePlatforms;

use App\Domain\Music\Platform;
use Symfony\Component\Uid\Ulid;

final readonly class HideReleasePlatforms
{
    /** @param list<Platform> $platforms */
    public function __construct(
        public Ulid $id,
        public array $platforms,
    ) {
    }
}
