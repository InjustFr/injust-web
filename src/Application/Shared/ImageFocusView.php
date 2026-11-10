<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Domain\Shared\ImageFocus;

final readonly class ImageFocusView
{
    private function __construct(
        public string $id,
        public int $x,
        public int $y,
    ) {
    }

    public static function of(ImageFocus $focus): self
    {
        return new self($focus->image(), $focus->x(), $focus->y());
    }
}
