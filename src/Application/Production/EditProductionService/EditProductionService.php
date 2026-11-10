<?php

declare(strict_types=1);

namespace App\Application\Production\EditProductionService;

use App\Domain\Production\ProductionServiceDetails;
use Symfony\Component\Uid\Ulid;

final readonly class EditProductionService
{
    public function __construct(
        public Ulid $id,
        public ProductionServiceDetails $details,
    ) {
    }
}
