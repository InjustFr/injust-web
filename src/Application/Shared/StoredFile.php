<?php

declare(strict_types=1);

namespace App\Application\Shared;

final readonly class StoredFile
{
    public function __construct(
        public string $id,
        public string $originalName,
        public int $size,
        public string $mimeType,
    ) {
    }
}
