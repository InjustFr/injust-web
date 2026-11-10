<?php

declare(strict_types=1);

namespace App\Presentation\Api\Images;

final readonly class FocusPayload
{
    public function __construct(
        public int $x,
        public int $y,
    ) {
    }
}
