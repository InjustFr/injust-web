<?php

declare(strict_types=1);

namespace App\Application\Music\DeleteSocialLink;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteSocialLink
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
