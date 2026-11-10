<?php

declare(strict_types=1);

namespace App\Application\Music\DeleteRelease;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteRelease
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
