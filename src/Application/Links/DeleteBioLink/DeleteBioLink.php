<?php

declare(strict_types=1);

namespace App\Application\Links\DeleteBioLink;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteBioLink
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
