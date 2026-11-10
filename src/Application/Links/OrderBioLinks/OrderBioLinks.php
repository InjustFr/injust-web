<?php

declare(strict_types=1);

namespace App\Application\Links\OrderBioLinks;

final readonly class OrderBioLinks
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
