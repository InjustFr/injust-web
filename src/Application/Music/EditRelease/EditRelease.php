<?php

declare(strict_types=1);

namespace App\Application\Music\EditRelease;

use App\Domain\Music\ReleaseDetails;
use Symfony\Component\Uid\Ulid;

final readonly class EditRelease
{
    public function __construct(
        public Ulid $id,
        public ReleaseDetails $details,
    ) {
    }
}
