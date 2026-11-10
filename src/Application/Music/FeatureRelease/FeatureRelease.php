<?php

declare(strict_types=1);

namespace App\Application\Music\FeatureRelease;

use Symfony\Component\Uid\Ulid;

final readonly class FeatureRelease
{
    public function __construct(
        public Ulid $id,
        public bool $featured,
    ) {
    }
}
