<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Beats\BeatFile;

final readonly class BeatPreviewView
{
    private function __construct(
        public string $id,
        public string $originalName,
        public int $size,
    ) {
    }

    public static function of(?BeatFile $file): ?self
    {
        return null === $file ? null : new self($file->id, $file->originalName, $file->size);
    }
}
