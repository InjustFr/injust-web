<?php

declare(strict_types=1);

namespace App\Application\Production\RemovePortfolioCover;

use Symfony\Component\Uid\Ulid;

final readonly class RemovePortfolioCover
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
