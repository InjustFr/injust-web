<?php

declare(strict_types=1);

namespace App\Application\Production\UploadPortfolioCover;

use Symfony\Component\Uid\Ulid;

final readonly class UploadPortfolioCover
{
    public function __construct(
        public Ulid $id,
        public string $path,
    ) {
    }
}
