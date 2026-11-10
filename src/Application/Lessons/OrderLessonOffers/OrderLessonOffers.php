<?php

declare(strict_types=1);

namespace App\Application\Lessons\OrderLessonOffers;

final readonly class OrderLessonOffers
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
