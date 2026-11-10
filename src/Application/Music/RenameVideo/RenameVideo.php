<?php

declare(strict_types=1);

namespace App\Application\Music\RenameVideo;

use Symfony\Component\Uid\Ulid;

final readonly class RenameVideo
{
    public function __construct(
        public Ulid $id,
        public string $title,
    ) {
    }
}
