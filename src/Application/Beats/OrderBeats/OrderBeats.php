<?php

declare(strict_types=1);

namespace App\Application\Beats\OrderBeats;

final readonly class OrderBeats
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
