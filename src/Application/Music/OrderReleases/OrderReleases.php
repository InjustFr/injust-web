<?php

declare(strict_types=1);

namespace App\Application\Music\OrderReleases;

final readonly class OrderReleases
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
