<?php

declare(strict_types=1);

namespace App\Application\Shared\SetImageFocus;

final readonly class SetImageFocus
{
    public function __construct(
        public string $image,
        public int $x,
        public int $y,
    ) {
    }
}
