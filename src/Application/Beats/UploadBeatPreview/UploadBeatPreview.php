<?php

declare(strict_types=1);

namespace App\Application\Beats\UploadBeatPreview;

use Symfony\Component\Uid\Ulid;

final readonly class UploadBeatPreview
{
    public function __construct(
        public Ulid $id,
        public string $path,
        public string $originalName,
    ) {
    }
}
