<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\ReleaseType;

final readonly class ReleaseLookup
{
    public function __construct(
        public string $upc,
        public string $title,
        public ReleaseType $type,
    ) {
    }
}
