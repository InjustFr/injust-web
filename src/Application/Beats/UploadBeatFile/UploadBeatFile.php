<?php

declare(strict_types=1);

namespace App\Application\Beats\UploadBeatFile;

use App\Domain\Beats\Deliverable;
use Symfony\Component\Uid\Ulid;

final readonly class UploadBeatFile
{
    public function __construct(
        public Ulid $id,
        public Deliverable $deliverable,
        public string $path,
        public string $originalName,
    ) {
    }
}
