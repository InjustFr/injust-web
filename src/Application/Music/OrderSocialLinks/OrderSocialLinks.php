<?php

declare(strict_types=1);

namespace App\Application\Music\OrderSocialLinks;

final readonly class OrderSocialLinks
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
