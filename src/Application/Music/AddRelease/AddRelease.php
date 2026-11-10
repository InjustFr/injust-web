<?php

declare(strict_types=1);

namespace App\Application\Music\AddRelease;

use App\Domain\Music\ReleaseDetails;

final readonly class AddRelease
{
    public function __construct(
        public ReleaseDetails $details,
    ) {
    }
}
