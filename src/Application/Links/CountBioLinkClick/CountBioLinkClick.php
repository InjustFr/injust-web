<?php

declare(strict_types=1);

namespace App\Application\Links\CountBioLinkClick;

use Symfony\Component\Uid\Ulid;

final readonly class CountBioLinkClick
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
