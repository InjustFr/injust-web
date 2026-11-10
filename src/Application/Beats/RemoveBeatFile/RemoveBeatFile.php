<?php

declare(strict_types=1);

namespace App\Application\Beats\RemoveBeatFile;

use App\Domain\Beats\Deliverable;
use Symfony\Component\Uid\Ulid;

final readonly class RemoveBeatFile
{
    public function __construct(
        public Ulid $id,
        public Deliverable $deliverable,
    ) {
    }
}
