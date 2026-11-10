<?php

declare(strict_types=1);

namespace App\Application\Beats\RemoveBeatCover;

use Symfony\Component\Uid\Ulid;

final readonly class RemoveBeatCover
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
