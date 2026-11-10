<?php

declare(strict_types=1);

namespace App\Application\Music\RefreshReleaseLinks;

use Symfony\Component\Uid\Ulid;

final readonly class RefreshReleaseLinks
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
