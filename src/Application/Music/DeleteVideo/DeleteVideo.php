<?php

declare(strict_types=1);

namespace App\Application\Music\DeleteVideo;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteVideo
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
