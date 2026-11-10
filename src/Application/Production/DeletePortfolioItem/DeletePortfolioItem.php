<?php

declare(strict_types=1);

namespace App\Application\Production\DeletePortfolioItem;

use Symfony\Component\Uid\Ulid;

final readonly class DeletePortfolioItem
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
