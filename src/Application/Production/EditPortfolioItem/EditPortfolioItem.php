<?php

declare(strict_types=1);

namespace App\Application\Production\EditPortfolioItem;

use App\Domain\Production\PortfolioItemDetails;
use Symfony\Component\Uid\Ulid;

final readonly class EditPortfolioItem
{
    public function __construct(
        public Ulid $id,
        public PortfolioItemDetails $details,
    ) {
    }
}
