<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class DownloadFile
{
    public function __construct(
        public string $path,
        public string $filename,
        public string $mimeType,
    ) {
    }
}
