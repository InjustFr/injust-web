<?php

declare(strict_types=1);

namespace App\Application\Production\OrderPortfolioItems;

final readonly class OrderPortfolioItems
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
