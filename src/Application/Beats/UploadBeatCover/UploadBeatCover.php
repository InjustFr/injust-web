<?php

declare(strict_types=1);

namespace App\Application\Beats\UploadBeatCover;

use Symfony\Component\Uid\Ulid;

final readonly class UploadBeatCover
{
    public function __construct(
        public Ulid $id,
        public string $path,
    ) {
    }
}
