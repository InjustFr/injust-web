<?php

declare(strict_types=1);

namespace App\Application\Beats\RemoveBeatPreview;

use Symfony\Component\Uid\Ulid;

final readonly class RemoveBeatPreview
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
