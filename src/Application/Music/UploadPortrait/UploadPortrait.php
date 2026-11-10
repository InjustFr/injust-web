<?php

declare(strict_types=1);

namespace App\Application\Music\UploadPortrait;

final readonly class UploadPortrait
{
    public function __construct(
        public string $path,
    ) {
    }
}
