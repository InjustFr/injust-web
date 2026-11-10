<?php

declare(strict_types=1);

namespace App\Application\Production\AddProductionService;

use App\Domain\Production\ProductionServiceDetails;

final readonly class AddProductionService
{
    public function __construct(
        public ProductionServiceDetails $details,
    ) {
    }
}
