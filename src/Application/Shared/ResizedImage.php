<?php

declare(strict_types=1);

namespace App\Application\Shared;

final readonly class ResizedImage
{
    public const string EXTENSION = 'webp';

    public function __construct(
        public string $bytes,
        public int $width,
        public int $height,
    ) {
    }
}
