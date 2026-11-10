<?php

declare(strict_types=1);

namespace App\Application\Music\OrderVideos;

final readonly class OrderVideos
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
