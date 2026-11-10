<?php

declare(strict_types=1);

namespace App\Application\Music\RemoveReleaseCover;

use Symfony\Component\Uid\Ulid;

final readonly class RemoveReleaseCover
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
