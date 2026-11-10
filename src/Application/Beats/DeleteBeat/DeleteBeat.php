<?php

declare(strict_types=1);

namespace App\Application\Beats\DeleteBeat;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteBeat
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
