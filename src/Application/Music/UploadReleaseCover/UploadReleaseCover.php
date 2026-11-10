<?php

declare(strict_types=1);

namespace App\Application\Music\UploadReleaseCover;

use Symfony\Component\Uid\Ulid;

final readonly class UploadReleaseCover
{
    public function __construct(
        public Ulid $id,
        public string $path,
    ) {
    }
}
