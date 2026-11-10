<?php

declare(strict_types=1);

namespace App\Application\Production\DeleteProductionService;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteProductionService
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
