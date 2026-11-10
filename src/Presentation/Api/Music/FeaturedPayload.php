<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

final readonly class FeaturedPayload
{
    public function __construct(public bool $featured = false)
    {
    }
}
