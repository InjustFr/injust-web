<?php

declare(strict_types=1);

namespace App\Application\Beats\DeleteLicenceTier;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteLicenceTier
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
