<?php

declare(strict_types=1);

namespace App\Application\Production\AddPortfolioItem;

use App\Domain\Production\PortfolioItemDetails;

final readonly class AddPortfolioItem
{
    public function __construct(
        public PortfolioItemDetails $details,
    ) {
    }
}
