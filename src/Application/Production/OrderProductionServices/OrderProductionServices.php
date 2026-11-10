<?php

declare(strict_types=1);

namespace App\Application\Production\OrderProductionServices;

final readonly class OrderProductionServices
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
