<?php

declare(strict_types=1);

namespace App\Application\Beats\OrderLicenceTiers;

final readonly class OrderLicenceTiers
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
